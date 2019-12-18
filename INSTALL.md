# WPForms EPFL Payonline

## Installation

  1. Install [WPForms].
  2. Activate the [Elite] or [Pro] version.
  3. Download and install [WPForms EPFL Payonline] [latest release].
  4. Activate it.

WPForms EPFL Payonline is now ready to be configured.

## Configuration

Both WPForms EPFL Payonline and the Payonline instance need to be configured
together:
  - The **Return URL** have to be set on the Payonline instance: if your 
    Event URL is https://idevfsd-test-conferences.epfl.ch, then the
    **Return URL** would be 
    https://idevfsd-test-conferences.epfl.ch/?EPFLPayonline.

    Please note the `?EPFLPayonline` parameter in the URL query string.

  - The **EPFL Payonline instance ID** have to be set for any forms that need 
    to use EPFL Payonline.
    ![file](https://github.com/epfl-idevelop/wpforms-epfl-payonline/raw/master/doc/img/WPForms-Payonline-Instance-ID-Highlighted.png)

WPForms EPFL Payonline is now ready to be used as payment gateway.

[WPForms EPFL Payonline]: https://github.com/epfl-idevelop/wpforms-epfl-payonline
[latest release]: https://github.com/epfl-idevelop/wpforms-epfl-payonline/releases/latest
[WPForms]: https://wpforms.com/
[Elite]: https://wpforms.com/checkout?edd_action=add_to_cart&download_id=290232&discount=SAVE50
[Pro]: https://wpforms.com/checkout?edd_action=add_to_cart&download_id=290008&discount=SAVE50