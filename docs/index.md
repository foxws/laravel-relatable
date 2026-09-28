---
title: Introduction
metadata:
  role: Documentation
  eyebrow: "Eloquent · Relations · Scoring"
  desc: "Relate Eloquent models to other models, with a base score and boost to control their priority."
  requires: "PHP ^8.3"
  laravel: "12.x / 13.x"
  licence: MIT
---

# Laravel Relatable

Relate Eloquent models to other models, of any type, and control how strongly
they relate. A tag can relate to other tags, a video to tags, a post to
videos — each relation is a row in the `relatables` table.

## Directed relations

Relations are directed. When *Action* relates to *Genre*, *Genre* doesn't relate to
*Action* unless you say so — and when it does, it gets a score of its own. Two
models can relate to each other, yet still differ in how much they do.

## Scoring

Each relation has a base `score` and a `boost`. Their product is the relation's
**weight**, which orders related models (highest first). See
[scoring.md](scoring.md).

- [Installation](installation.md)
- [Usage](usage.md)
- [Scoring](scoring.md)
- [Configuration](configuration.md)
