# WPForms EPFL Payonline

[WPForms EPFL Payonline] is a [WPForms] addon that allows the
use of [EPFL Payonline] as a payment gateway.

_DISCLAIMER: This addon is only useful in the [EPFL] ecosystem. Therefore, any 
attempt to use it "as is" without any modification will most certainly fail. 
Consider yourself warned..._

## Pre-requisites

  1. WPForms [Elite] or [Pro] is required to activate the use of
     payment, such as Stripe, Paypal or EPFL Payonline.
  2. You need the '[accred](https://accred.epfl.ch/)' right named 'Payonline' 
     on the relevant EPFL unit.

## Installation & Configuration

Please read the [INSTALL.md] file.

## Contributing

You probably already know the drill: [CONTRIBUTING.md].

## Help and support

Please raise an [issue] with verbatim comments and steps to reproduce.

## Development

Once you have a running WordPress environment who can sends emails (you can use
the [wp-dev] repo), install WPForms [Elite] or [Pro] and git clone this repo in
the `wp-content/plugins` folder. Be sure to activate it.

### Releasing

This project carries some comfort scripts that made the releasing process
easier, such as automated version, updated translation file, package creation,
git support, ... 

You can have a look to the [bump-version.sh], [create-gh-release.sh] and
[Makefile] files.

_Note:_ you need to create a [personal access token] and add it to your
`WPEP_GH_TOKEN` environment var, in order that the GitHub release can be created.

#### Here are some useful commands:

1. **Create a release**: `make release`  
   It will run some checks, bump version, create/update the translation
   (gettext) files, create the zip package, add and commit (if requested) files,
   create a tag according to the version and finally call the
   [create-gh-release.sh] script. It should result in a freshly created [latest
   release].

1. **Update the translation**: `make pot`  
   It will use the `wp i18n make-pot` command and the gettext's `msgmerge`,
   `msginit` and `msgfmt` boogaloo to create or update the
   `languages/wpforms-epfl-payonline.pot`,
   `languages/wpforms-epfl-payonline-fr_FR.po`,
   `languages/wpforms-epfl-payonline-fr_FR.mo` files.

1. **Create a new package**: `make zip`  
   Create a zip archive in the `/builds` directory, excluding unwanted files and
   directories. Also create 2 symbolic links `wpforms-epfl-payonline.zip` and
   `latest.zip` pointing to the last released version.

1. **Change the version**: `make version` (default to patch)  
   This will change version number in [wpforms-epfl-payonline.php], using [semver](https://semver.org/). Available options are:
  * `make version-patch`
  * `make version-minor`
  * `make version-major`



[EPFL]: https://www.epfl.ch
[EPFL Payonline]: https://payonline.epfl.ch
[WPForms EPFL Payonline]: https://github.com/epfl-si/wpforms-epfl-payonline
[latest release]: https://github.com/epfl-si/wpforms-epfl-payonline/releases/latest
[issue]: https://github.com/epfl-si/wpforms-epfl-payonline/issues
[WPForms]: https://wpforms.com/
[Elite]: https://wpforms.com/checkout?edd_action=add_to_cart&download_id=290232&discount=SAVE50
[Pro]: https://wpforms.com/checkout?edd_action=add_to_cart&download_id=290008&discount=SAVE50
[INSTALL.md]: https://github.com/epfl-si/wpforms-epfl-payonline/blob/master/INSTALL.md
[CONTRIBUTING.md]: https://github.com/epfl-si/wpforms-epfl-payonline/blob/master/CONTRIBUTING.md
[wp-dev]: https://github.com/epfl-si/wp-dev
[personal access token]: https://help.github.com/en/github/authenticating-to-github/creating-a-personal-access-token-for-the-command-line
[bump-version.sh]: https://github.com/epfl-si/wpforms-epfl-payonline/blob/master/bump-version.sh
[create-gh-release.sh]: https://github.com/epfl-si/wpforms-epfl-payonline/blob/master/create-gh-release.sh
[Makefile]: https://github.com/epfl-si/wpforms-epfl-payonline/blob/master/Makefile
[wpforms-epfl-payonline.php]: https://github.com/epfl-si/wpforms-epfl-payonline/blob/master/wpforms-epfl-payonline.php
