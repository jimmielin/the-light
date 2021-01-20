# ng-gateway
Next Generation Hole Gateway Application

## Installation
* Install before all other apps.
* `git clone`
* Check `.env` is correct.
* `php artisan migrate`
* Generate assets: `npm run production`

## Assets
### PHP/Hack
* Controllers: `app/Http`
* Model: `app/`
* Views: `resources/views`

### Javascript/JSX/React
* `resources/js`
* Regenerate by running `npm run production` (for dev, `npm run dev` or `npm run watch`)