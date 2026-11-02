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
$ php -S localhost:8000 -t public
```

La base de datos SQLite se guarda en `var/schema.sqlite` (el directorio `var/` debe
ser escribible por el servidor web). Cambia `APP_SECRET` en un `.env.local` antes de
desplegar en producción.

La aplicación cuenta con una parte de administración o *backend* accesible en la
URL `/admin` (autenticación HTTP básica). El usuario es `admin` y su contraseña `1234`;
puedes cambiarla en `config/packages/security.yaml` (genera un hash con
`php bin/console security:hash-password`).
