# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 0.x (latest minor) | ✅ |

## Reporting a vulnerability

Please **do not** open a public issue for security problems. Email **alicia@arzcode.com** with a description, reproduction steps and the affected version. You will get an acknowledgement within a few days and a fix or mitigation plan as soon as possible; credit is given in the changelog unless you prefer otherwise.

## Notes for integrators

- Record mode, preview mode and the tour resource are only available to users passing the plugin's `authorize()` closure. The default is *nobody*: make the closure as strict as your app needs.
- Step bodies are rendered as HTML (they are authored by trusted staff through the rich editor or record mode). Do not let untrusted users author steps.
- `data-tour` keys are sanitised to `[A-Za-z0-9_\-.:]` before being rendered as attributes.
