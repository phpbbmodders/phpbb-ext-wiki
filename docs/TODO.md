# TODO

Ideas not yet built, practical and speculative alike.

## Article creation templates

Right now a new article always starts blank. Offer a starting-point picker
when creating an article:

- **Blank** — today's behavior, an empty edit form.
- **Generic (Wikipedia-style)** — pre-fill the message body with a common
  encyclopedia-article skeleton (e.g. an intro paragraph placeholder,
  `Overview`, `History`, `References` section headings) so new articles
  don't start from a truly empty box.

The template picker lives on the edit page itself (not the overview page's
quick-create box, which still just takes you straight to a blank edit form
and lets you pick there).

Templates are admin-configurable: admins can add new templates beyond the
built-in "Blank" and "Generic" ones, via an ACP page.

Still needs deciding before implementation:

- What a template is allowed to contain (plain text with placeholders?
  BBCode? anything the message parser accepts?) and how that's authored in
  the ACP form.
- What sanitization that admin-authored template content needs before it's
  inserted into the edit form's message body — it's admin-authored rather
  than end-user input, but still ends up in content regular members see and
  can build on, so it shouldn't get a free pass on the same checks normal
  article content goes through.
