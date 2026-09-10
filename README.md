# Users+

Todo lo que tiene que ver con las personas que usan un sitio de WordPress:
qué datos se les piden, cómo entran, qué ven de lo suyo y qué pueden hacer
con eso. En casi todos los sitios se resuelve de nuevo cada vez, con cuatro
plugins que no se hablan entre sí. Acá vive una vez.

## Qué hace

- **Campos de usuario.** Se definen desde el escritorio: nombre, tipo, si es
  obligatorio, dónde va, y quién lo puede cambiar y cuántas veces. Los que ya
  trae WordPress —nombre y apellido— están en la misma lista y con las mismas
  reglas.
- **Área de cuenta en el frente.** Inicio, Mis datos, Cuentas vinculadas,
  Seguridad, Privacidad y Notificaciones, con menú arriba o al costado. Las
  secciones se renombran, se reordenan, se apagan y se agregan; una sección
  propia es un nombre, una dirección y un shortcode.
- **Cómo entra la gente.** Enlace por correo sin contraseña, usuario y
  contraseña, o las dos. Con control de qué pasa con el registro nativo de
  WordPress y con su pantalla de perfil.
- **Login social.** Doce proveedores con guía paso a paso para cada consola,
  botones con las marcas de verdad y una prueba en vivo antes de prenderlo.
- **Verificación en dos pasos.** Código por correo, aplicación autenticadora
  con QR, y códigos de respaldo. Con política por rol y por forma de entrar.
- **Passkeys.** WebAuthn, con nombre por llave y política de dónde se aceptan.
- **Sesiones.** Cuánto duran, dónde están abiertas y cómo se cierran.
- **Privacidad.** Los pedidos de exportación y borrado que WordPress ya sabe
  atender, puestos donde la gente los busca.

Nada de esto depende de otro plugin. Lo que es de otro —un curso, una
membresía, un foro— entra por un filtro o por un shortcode.

## Principios

1. **Lo que se apaga en el escritorio desaparece del frente.** Sin ninguna red
   social prendida no hay sección «Cuentas vinculadas»; sin permiso para
   descargar datos ni borrar la cuenta no hay sección «Privacidad».
2. **El plugin no sabe qué es un curso.** Ni una membresía, ni un foro. Lo que
   no es suyo se agrega desde afuera y se puede sacar sin tocarlo.
3. **Anda en cualquier tema.** Trae sus estilos, sus plantillas se
   sobrescriben desde el tema, y sus colores salen de propiedades CSS que el
   sitio redefine.
4. **Nada de lo que se muestra es mentira.** Si un aviso dice que el segundo
   factor no se está pidiendo, es porque no se está pidiendo por ninguna de
   las puertas que el sitio tiene abiertas.

## Desarrollo

```bash
make install   # dependencias de desarrollo
make check     # todo lo que corre CI
make test      # sólo los tests
make lint      # PHP_CodeSniffer con los estándares de WordPress
```

Ver [docs/development.md](docs/development.md).

## Licencia

GPL-2.0-or-later. Ver [LICENSE](LICENSE).
