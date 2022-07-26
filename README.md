# ps-monitor-module-releases

# Install

```
composer install
```

# Usage

```
php generate.php {token}
```

Token must be a valid GitHub API token.

# Browse

Generated static HTML is hosted on https://matks.github.io/ps-monitor-module-releases/

A GitHub Action runs `generate.php` to generate the `index.html` file then it pushes it to branch `gh-pages`. Branch `gh-pages` is configured to be deployed by GitHub Pages.