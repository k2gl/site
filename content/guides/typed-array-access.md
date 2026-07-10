---
title: "Typed access to messy arrays"
description: "Replace isset() ladders over query strings, config and decoded JSON with typed reads, defaults, and errors that say what's actually missing."
order: 60
---

Every PHP codebase has this somewhere:

```php
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
```

[`k2gl/array-reader`](/packages/array-reader) turns it into a typed read — and
keeps PHPStan/Psalm green, because nothing comes back as `mixed`:

```php
use K2gl\ArrayReader\ArrayReader;

$request = ArrayReader::of($_GET);

$page   = $request->intOr('page', 1);    // "5" -> 5; 1 when absent or not a number
$email  = $request->string('email');     // throws if missing or not producible
$active = $request->bool('active');      // "on", "yes", "1" -> true
```

Every scalar type comes as a pair: strict `int()` throws (`MissingKeyException`,
`TypeMismatchException` — with the key name in the message), lenient `intOr()`
returns your default. Nested structures read through `nested()`:

```php
$config = ArrayReader::fromJson($raw);

$config->require(['host', 'port']);                   // assert shape up front
$dsn = $config->nested('database')->string('host');   // a reader over the sub-array
```

## Three readers, one dial: how much casting

- **`ArrayReader`** — safe casting, the default: `"42"` becomes `42`, but lossy or
  ambiguous conversions are rejected (`"9.99"` is not an int). For `$_GET`,
  `$_POST`, CSV, env.
- **`StrictArrayReader`** — the value must already *be* the type. For decoded
  JSON you trust.
- **`LooseArrayReader`** — PHP's native cast, never rejects a scalar. Only when
  you explicitly want that.

Same methods on all three, so switching the dial doesn't rewrite call sites.

Full method list (floats, bools, lists, `nestedList()` for arrays of objects) is
on the [package page](/packages/array-reader). Validating whole schemas or
hydrating DTOs is a different job — reach for a validator or serializer there;
this is for *reading a few values properly*.
