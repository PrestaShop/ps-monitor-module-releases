# PrestaShop Module Releases Monitoring

Little PHP application that fetches PrestaShop modules git data to find which modules need a release.

Output is a HTML page `index.html`.

It is designed to be run by a GitHub Action that pushes the HTML page to a branch `gh-pages`. Branch `gh-pages` is configured to be deployed by GitHub Pages.

# Install

```
composer install
```

# Usage

```
php generate.php {token}
```

Token must be a valid GitHub API token.

The GitHub Action uses [GITHUB_TOKEN](https://docs.github.com/en/actions/security-guides/automatic-token-authentication) secret.

# Browse

Generated static HTML is hosted on https://build.prestashop.com/ps-monitor-module-releases/

A GitHub Action runs `generate.php` to generate the `index.html` file in `docs/` folder then it pushes it to branch `gh-pages`.