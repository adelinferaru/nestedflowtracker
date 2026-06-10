# Security Policy

## Supported versions

| Version | Supported |
| ------- | --------- |
| 3.x     | ✅        |
| < 3.0   | ❌        |

## Reporting a vulnerability

If you discover a security issue in NestedFlowTracker, please **do not open a
public issue**. Email **adelin.feraru@gmail.com** with the details, or use
[GitHub's private vulnerability reporting](https://github.com/adelinferaru/nestedflowtracker/security/advisories/new)
on this repository.

You can expect an acknowledgement within a few days. Once a fix is released,
the issue will be disclosed in the [changelog](changelog.md).

## Scope notes

- The built-in viewer is opt-in and gated (`local` environment, or a `viewFlow`
  gate everywhere else). Misconfigured gates in *your* application are outside
  the package's control — treat the viewer like any other admin surface.
- Spans can carry arbitrary `context`/`result` payloads. The package stores
  them as-is in your database; do not put secrets in span payloads.
