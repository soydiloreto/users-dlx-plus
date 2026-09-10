<?php
/**
 * Un código QR, en SVG, sin dependencias.
 *
 * Hace falta para una sola cosa: mostrar la URI `otpauth://` para que la
 * persona la escanee con su aplicación autenticadora. Traer una librería
 * entera —o peor, mandarle la URI a un servicio externo que genere la
 * imagen, que es lo que hacen varios plugins y significa filtrar el secreto
 * del segundo factor a un tercero— es desproporcionado.
 *
 * Alcance a propósito: modo byte, nivel de corrección L, versiones 1 a 10 y
 * máscara fija. Con eso entran hasta 174 caracteres, que es de sobra para una
 * URI de TOTP, y se evita la mitad del estándar. La máscara fija es legal: el
 * estándar exige que la información de formato diga cuál se usó, no que se
 * elija la mejor.
 *
 * Todo lo que sigue es ISO/IEC 18004. Las tablas son del estándar.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

/** Cuántos bytes de datos entran por versión, en modo byte y nivel L. */
function upfw_qr_capacity(): array {
	return array(
		1  => 17,
		2  => 32,
		3  => 53,
		4  => 78,
		5  => 106,
		6  => 134,
		7  => 154,
		8  => 192,
		9  => 230,
		10 => 271,
	);
}

/**
 * Por versión: [ codewords de corrección por bloque, bloques grupo 1,
 * codewords de datos por bloque grupo 1, bloques grupo 2, codewords grupo 2 ].
 * Nivel L.
 */
function upfw_qr_blocks(): array {
	return array(
		1  => array( 7, 1, 19, 0, 0 ),
		2  => array( 10, 1, 34, 0, 0 ),
		3  => array( 15, 1, 55, 0, 0 ),
		4  => array( 20, 1, 80, 0, 0 ),
		5  => array( 26, 1, 108, 0, 0 ),
		6  => array( 18, 2, 68, 0, 0 ),
		7  => array( 20, 2, 78, 0, 0 ),
		8  => array( 24, 2, 97, 0, 0 ),
		9  => array( 30, 2, 116, 0, 0 ),
		10 => array( 18, 2, 68, 2, 69 ),
	);
}

/** Dónde van los patrones de alineación, por versión. */
function upfw_qr_alignment(): array {
	return array(
		1  => array(),
		2  => array( 6, 18 ),
		3  => array( 6, 22 ),
		4  => array( 6, 26 ),
		5  => array( 6, 30 ),
		6  => array( 6, 34 ),
		7  => array( 6, 22, 38 ),
		8  => array( 6, 24, 42 ),
		9  => array( 6, 26, 46 ),
		10 => array( 6, 28, 50 ),
	);
}

/**
 * La información de versión, para 7 en adelante.
 *
 * Son 18 bits calculados con un BCH que no hace falta implementar: son cuatro
 * valores y están en el estándar.
 */
function upfw_qr_version_info(): array {
	return array(
		7  => '000111110010010100',
		8  => '001000010110111100',
		9  => '001001101010011001',
		10 => '001010010011010011',
	);
}

/**
 * La información de formato para nivel L y máscara 0.
 *
 * Uno solo, porque el nivel y la máscara son fijos. También sale del estándar.
 */
const UPFW_QR_FORMAT = '111011111000100';

/* ── Reed-Solomon sobre GF(256) ────────────────────────────────────── */

/** Las tablas de exponentes y logaritmos del campo, calculadas una vez. */
function upfw_qr_gf(): array {
	static $tables = null;

	if ( null !== $tables ) {
		return $tables;
	}

	$exp = array_fill( 0, 512, 0 );
	$log = array_fill( 0, 256, 0 );
	$x   = 1;

	for ( $i = 0; $i < 255; $i++ ) {
		$exp[ $i ] = $x;
		$log[ $x ] = $i;
		$x       <<= 1;

		// El polinomio primitivo del QR es 0x11D.
		if ( $x & 0x100 ) {
			$x ^= 0x11D;
		}
	}

	for ( $i = 255; $i < 512; $i++ ) {
		$exp[ $i ] = $exp[ $i - 255 ];
	}

	$tables = array( $exp, $log );

	return $tables;
}

/** El polinomio generador para n codewords de corrección. */
function upfw_qr_generator( int $n ): array {
	[ $exp, $log ] = upfw_qr_gf();

	$poly = array( 1 );

	for ( $i = 0; $i < $n; $i++ ) {
		$next = array_fill( 0, count( $poly ) + 1, 0 );

		foreach ( $poly as $j => $coef ) {
			$next[ $j ] ^= $coef;

			if ( 0 !== $coef ) {
				$next[ $j + 1 ] ^= $exp[ ( $log[ $coef ] + $i ) % 255 ];
			}
		}

		$poly = $next;
	}

	return $poly;
}

/** Los codewords de corrección de un bloque de datos. */
function upfw_qr_ec( array $data, int $n ): array {
	[ $exp, $log ] = upfw_qr_gf();

	$gen  = upfw_qr_generator( $n );
	$rest = array_merge( $data, array_fill( 0, $n, 0 ) );

	for ( $i = 0; $i < count( $data ); $i++ ) {
		$coef = $rest[ $i ];

		if ( 0 === $coef ) {
			continue;
		}

		foreach ( $gen as $j => $g ) {
			if ( 0 !== $g ) {
				$rest[ $i + $j ] ^= $exp[ ( $log[ $g ] + $log[ $coef ] ) % 255 ];
			}
		}
	}

	return array_slice( $rest, count( $data ) );
}

/* ── La matriz ─────────────────────────────────────────────────────── */

/**
 * La matriz de módulos de un texto: true = negro.
 *
 * @return array<int, array<int, bool>>|null Null si el texto no entra.
 */
function upfw_qr_matrix( string $text ): ?array {
	$bytes  = array_map( 'ord', str_split( $text ) );
	$length = count( $bytes );

	$version = 0;

	foreach ( upfw_qr_capacity() as $v => $max ) {
		if ( $length <= $max ) {
			$version = $v;
			break;
		}
	}

	if ( 0 === $version ) {
		return null;
	}

	[ $ec_per_block, $g1_blocks, $g1_words, $g2_blocks, $g2_words ] = upfw_qr_blocks()[ $version ];

	$total_data = $g1_blocks * $g1_words + $g2_blocks * $g2_words;

	// El flujo de bits: modo (0100), longitud, los datos, y el relleno.
	// El campo de longitud es de 8 bits hasta la versión 9 y de 16 desde la 10.
	$bits  = '0100';
	$bits .= str_pad( decbin( $length ), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT );

	foreach ( $bytes as $byte ) {
		$bits .= str_pad( decbin( $byte ), 8, '0', STR_PAD_LEFT );
	}

	// Terminador de hasta cuatro ceros y relleno hasta byte completo.
	$bits .= str_repeat( '0', min( 4, $total_data * 8 - strlen( $bits ) ) );
	$bits .= str_repeat( '0', ( 8 - strlen( $bits ) % 8 ) % 8 );

	// Y después, los dos bytes de relleno alternados que manda el estándar.
	$padding = array( 0xEC, 0x11 );
	$i       = 0;

	while ( strlen( $bits ) < $total_data * 8 ) {
		$bits .= str_pad( decbin( $padding[ $i % 2 ] ), 8, '0', STR_PAD_LEFT );
		++$i;
	}

	$codewords = array_map( 'bindec', str_split( $bits, 8 ) );

	// Se parte en bloques, se calcula la corrección de cada uno, y después se
	// intercalan: primero el codeword 0 de cada bloque, después el 1, etc.
	$data_blocks = array();
	$ec_blocks   = array();
	$offset      = 0;

	foreach ( array( array( $g1_blocks, $g1_words ), array( $g2_blocks, $g2_words ) ) as [$blocks, $words] ) {
		for ( $b = 0; $b < $blocks; $b++ ) {
			$block         = array_slice( $codewords, $offset, $words );
			$offset       += $words;
			$data_blocks[] = $block;
			$ec_blocks[]   = upfw_qr_ec( $block, $ec_per_block );
		}
	}

	$stream = array();

	for ( $i = 0; $i < max( $g1_words, $g2_words ); $i++ ) {
		foreach ( $data_blocks as $block ) {
			if ( isset( $block[ $i ] ) ) {
				$stream[] = $block[ $i ];
			}
		}
	}

	for ( $i = 0; $i < $ec_per_block; $i++ ) {
		foreach ( $ec_blocks as $block ) {
			$stream[] = $block[ $i ];
		}
	}

	$final = '';

	foreach ( $stream as $codeword ) {
		$final .= str_pad( decbin( $codeword ), 8, '0', STR_PAD_LEFT );
	}

	return upfw_qr_place( $version, $final );
}

/** Dibuja la matriz: patrones fijos, datos y máscara. */
function upfw_qr_place( int $version, string $bits ): array {
	$size = 17 + 4 * $version;

	$matrix   = array_fill( 0, $size, array_fill( 0, $size, false ) );
	$reserved = array_fill( 0, $size, array_fill( 0, $size, false ) );

	$set = static function ( int $r, int $c, bool $dark ) use ( &$matrix, &$reserved ): void {
		$matrix[ $r ][ $c ]   = $dark;
		$reserved[ $r ][ $c ] = true;
	};

	// Los tres cuadrados de las esquinas, con su separador.
	foreach ( array( array( 0, 0 ), array( 0, $size - 7 ), array( $size - 7, 0 ) ) as [$row, $col] ) {
		for ( $r = -1; $r <= 7; $r++ ) {
			for ( $c = -1; $c <= 7; $c++ ) {
				if ( $row + $r < 0 || $row + $r >= $size || $col + $c < 0 || $col + $c >= $size ) {
					continue;
				}

				// Fuera del cuadrado de 7×7 es el separador, que va siempre en
				// blanco: sin eso, las esquinas del separador salían negras y
				// ningún lector encontraba el patrón.
				$dentro = $r >= 0 && $r <= 6 && $c >= 0 && $c <= 6;
				$borde  = 0 === $r || 6 === $r || 0 === $c || 6 === $c;
				$centro = $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4;

				$set( $row + $r, $col + $c, $dentro && ( $borde || $centro ) );
			}
		}
	}

	// Los patrones de alineación, salvo donde chocan con los de las esquinas.
	$centres = upfw_qr_alignment()[ $version ];

	foreach ( $centres as $row ) {
		foreach ( $centres as $col ) {
			$esquina = ( 6 === $row && 6 === $col )
				|| ( 6 === $row && $col === $size - 7 )
				|| ( $row === $size - 7 && 6 === $col );

			if ( $esquina ) {
				continue;
			}

			for ( $r = -2; $r <= 2; $r++ ) {
				for ( $c = -2; $c <= 2; $c++ ) {
					$set( $row + $r, $col + $c, 2 === max( abs( $r ), abs( $c ) ) || ( 0 === $r && 0 === $c ) );
				}
			}
		}
	}

	// Las dos líneas punteadas.
	for ( $i = 8; $i < $size - 8; $i++ ) {
		$set( 6, $i, 0 === $i % 2 );
		$set( $i, 6, 0 === $i % 2 );
	}

	// La información de formato va en dos copias. El bit 0 es el menos
	// significativo, y cada copia lo reparte distinto: una baja por la columna
	// 8 y la otra corre por la fila 8. Las dos posiciones salen del estándar y
	// no se pueden deducir; están escritas tal cual.
	$format = UPFW_QR_FORMAT;

	for ( $i = 0; $i < 15; $i++ ) {
		$bit = '1' === $format[ 14 - $i ];

		// Copia vertical.
		if ( $i < 6 ) {
			$set( $i, 8, $bit );
		} elseif ( $i < 8 ) {
			$set( $i + 1, 8, $bit );
		} else {
			$set( $size - 15 + $i, 8, $bit );
		}

		// Copia horizontal.
		if ( $i < 8 ) {
			$set( 8, $size - 1 - $i, $bit );
		} elseif ( 8 === $i ) {
			$set( 8, 7, $bit );
		} else {
			$set( 8, 14 - $i, $bit );
		}
	}

	// El módulo que siempre es negro. Va después de la información de formato
	// porque cae justo encima de uno de sus lugares.
	$set( $size - 8, 8, true );

	// La información de versión, de la 7 en adelante.
	if ( $version >= 7 ) {
		$info = upfw_qr_version_info()[ $version ];

		for ( $i = 0; $i < 18; $i++ ) {
			$bit = '1' === $info[ 17 - $i ];
			$r   = intdiv( $i, 3 );
			$c   = $i % 3;

			$set( $r, $size - 11 + $c, $bit );
			$set( $size - 11 + $c, $r, $bit );
		}
	}

	// Y ahora los datos: en zigzag de a dos columnas, de abajo a la derecha
	// hacia arriba, salteando la columna 6 que es la línea punteada.
	$index = 0;
	$total = strlen( $bits );
	$up    = true;

	for ( $col = $size - 1; $col > 0; $col -= 2 ) {
		if ( 6 === $col ) {
			--$col;
		}

		for ( $i = 0; $i < $size; $i++ ) {
			$row = $up ? $size - 1 - $i : $i;

			foreach ( array( $col, $col - 1 ) as $c ) {
				if ( $reserved[ $row ][ $c ] ) {
					continue;
				}

				$bit = $index < $total && '1' === $bits[ $index ];
				++$index;

				// Máscara 0: se invierte donde (fila + columna) es par.
				$matrix[ $row ][ $c ] = 0 === ( $row + $c ) % 2 ? ! $bit : $bit;
			}
		}

		$up = ! $up;
	}

	return $matrix;
}

/**
 * El QR de un texto, como SVG listo para imprimir.
 *
 * @param int $size Lado en píxeles.
 */
function upfw_qr_svg( string $text, int $size = 220 ): string {
	$matrix = upfw_qr_matrix( $text );

	if ( null === $matrix ) {
		return '';
	}

	$modules = count( $matrix );
	// Cuatro módulos de margen: el estándar los pide y sin ellos hay lectores
	// que no encuentran el código.
	$quiet = 4;
	$side  = $modules + $quiet * 2;

	$path = '';

	foreach ( $matrix as $r => $row ) {
		foreach ( $row as $c => $dark ) {
			if ( $dark ) {
				$path .= sprintf( 'M%d %dh1v1h-1z', $c + $quiet, $r + $quiet );
			}
		}
	}

	return sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%2$d" height="%2$d" shape-rendering="crispEdges" role="img">'
			. '<rect width="%1$d" height="%1$d" fill="#ffffff"/><path d="%3$s" fill="#000000"/></svg>',
		$side,
		$size,
		$path
	);
}
