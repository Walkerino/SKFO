# SKFO

SKFO.RU is a ProcessWire-based travel portal focused on the North Caucasus.

## Stack

- ProcessWire CMS (custom templates in `public/site/templates`)
- PHP 8.2
- DDEV (nginx-fpm + MariaDB 11.8)

## Content Management

The custom Content Center (`/content-admin/`) is used for operational editing of:
- tours
- hotels
- articles
- places
- hotel placements on `/hotels/`

Core editing still works via ProcessWire admin (`/processwire/`).


# Run PHP syntax check for key templates
php -l public/site/templates/hotels.php
php -l public/site/templates/hotel.php
```
