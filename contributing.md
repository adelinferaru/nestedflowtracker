# Contributing

Contributions are welcome and will be fully credited.

Contributions are accepted via Pull Requests on [Github](https://github.com/adelinferaru/nestedflowtracker).

The [ROADMAP](ROADMAP.md) lists what's planned next (e.g. an OpenTelemetry exporter, pluggable
storage drivers, and a performance pass) — good starting points if you're looking for something
to pick up.

## Local development

```bash
composer install
composer test      # PHPUnit via orchestra/testbench
composer analyse   # PHPStan (larastan) level 6
```

## Pull Requests

- **Add tests!** - Your patch won't be accepted if it doesn't have tests.

- **Document any change in behaviour** - Make sure the `readme.md` and any other relevant documentation are kept up-to-date.

- **Consider our release cycle** - We try to follow [SemVer v2.0.0](http://semver.org/). Randomly breaking public APIs is not an option.

- **One pull request per feature** - If you want to do more than one thing, send multiple pull requests.

- **Send coherent history** - Make sure each individual commit in your pull request is meaningful. If you had to make multiple intermediate commits while developing, please [squash them](http://www.git-scm.com/book/en/v2/Git-Tools-Rewriting-History#Changing-Multiple-Commit-Messages) before submitting.


**Happy coding**!
