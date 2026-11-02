# README #

---

Este repositorio contiene una pequeña aplicación web de prueba realizada originalmente con Silex (hoy migrada a Symfony 7) que usé como demostración en la [Cuarta BetaBeers de Córdoba](http://betabeers.com/event/4-betabeers-cordoba-626/). Trata de un blog muy simple, con un frontend y un backend sencillo para la creación de nuevas entradas.

La aplicación está basada en la aplicación [Bilbostack](https://github.com/javiereguiluz/bilbostack) realizada por [Javier Eguiluz](https://twitter.com/javiereguiluz), al cual agradezco que me haya dejado usar su material como apoyo a mi charla. Podéis encontrar [más material sobre Silex](http://symfony.es/noticias/2013/01/30/silex-desarrollo-web-%C3%A1gil-y-profesional-con-php/) en su página web.

**Requisitos**: PHP >= 8.2 con las extensiones `pdo_sqlite`, `ctype` e `iconv`, y [Composer](https://getcomposer.org).

**Cómo instalar el proyecto**

```
$ git clone https://github.com/mpgprofe/miniblog.git
$ cd miniblog
$ composer install
$ php bin/console create-schema
$ php bin/console app:create-user admin "Administrador"   # pide la contraseña (mín. 8 caracteres)
$ php -S localhost:8000 -t public
```

`create-schema` también actualiza bases de datos de versiones anteriores (las entradas
existentes quedan como publicadas). La base de datos SQLite se guarda en `var/schema.sqlite`
(el directorio `var/` debe ser escribible por el servidor web). Cambia `APP_SECRET` en un
`.env.local` antes de desplegar en producción.

**Usuarios y roles** (acceso en `/login`, panel en `/admin`)

| Rol | Puede |
|---|---|
| Alumno | Crear entradas como borrador o enviarlas a revisión; editar/borrar solo las suyas mientras no estén publicadas. |
| Docente | Todo lo anterior sobre cualquier entrada, y publicarlas o despublicarlas. |
| Administrador | Todo lo anterior, más gestionar usuarios y categorías. |

Para crear más usuarios desde la consola: `php bin/console app:create-user NOMBRE "Nombre completo" --role=ROLE_TEACHER`
(roles: `ROLE_ADMIN`, `ROLE_TEACHER`, `ROLE_STUDENT`). El administrador también puede hacerlo desde `/admin/users/`.
