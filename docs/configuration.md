---
section: Getting Started
order: 4
---

# Configuration

Publish the config file with:

```bash
php artisan vendor:publish --tag="relatable-config"
```

| Key | Default | Description |
| --- | --- | --- |
| `models.relatable` | `Foxws\Relatable\Models\Relatable` | The model for relation rows. |
| `migrations` | `true` | Run the package's migration automatically. |
| `defaults.score` | `1.0` | Score of a new relation when none is given. |
| `defaults.boost` | `1.0` | Boost of a new relation when none is given. |
| `delete_on_model_delete` | `true` | Delete a model's relations, in both directions, when it's deleted. |

## Custom model

Set `models.relatable` to your own model to add behavior. It must extend
`Foxws\Relatable\Models\Relatable`:

```php
use Foxws\Relatable\Models\Relatable;

class Related extends Relatable
{
    //
}
```

## Using an existing table

Your model may set its own `$table`. Every relation and the trait resolve the
table from the configured model:

```php
class Related extends Relatable
{
    protected $table = 'related';
}
```

The package's migration always creates `relatables`, so set `migrations` to
`false` and create (or keep) that table yourself. It needs these columns:

| Column | Type |
| --- | --- |
| `id` | primary key |
| `relatable_type`, `relatable_id` | morph (the model a relation starts from) |
| `related_type`, `related_id` | morph (the model it points to) |
| `score`, `boost` | float, not null, default `1` |
| `options` | json, nullable |
| `created_at`, `updated_at` | timestamps |

Add a unique index on the four morph columns.
