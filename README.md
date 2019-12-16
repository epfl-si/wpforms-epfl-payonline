# WPForms EPFL Payonline

[WPForms EPFL Payonline] is a [WPForms] addon that allows the
use of [EPFL Payonline] as a payment gateway.

_DISCLAIMER: This addon is only useful in the [EPFL] ecosystem. Therefore, any attempt to use it "as is" without any modification will most certainly fail. Consider yourself warned..._

## Pre-requisites

  1. WPForms [Elite] or [Pro] is required to activate the use of
     payment, such as Stripe, Paypal or EPFL Payonline.
  2. You need the '[accred](https://accred.epfl.ch/)' right named 'Payonline' 
     on the relevant EPFL unit.

## Installation

  1. Install [WPForms].
  2. Activate the [Elite] or [Pro] version.
  3. Download and install [WPForms EPFL Payonline] [latest release] ([Download latest]).
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
    ![file](doc/img/WPForms-Payonline-Instance-ID-Highlighted.png)

WPForms EPFL Payonline is now ready to be used as payment gateway.


## Development

WIP

## Contributing

You probably already know the drill → [CONTRIBUTING.md](CONTRIBUTING.md)

## Help and support

Please raise an [issue] with verbatim comments and steps to reproduce.


[EPFL]: https://www.epfl.ch
[EPFL Payonline]: https://payonline.epfl.ch
[WPForms EPFL Payonline]: https://github.com/epfl-idevelop/wpforms-epfl-payonline
[latest release]: https://github.com/epfl-idevelop/wpforms-epfl-payonline/releases/latest
[Download latest]: https://github.com/epfl-idevelop/wpforms-epfl-payonline/releases/latest/download/wpforms-epfl-payonline.zip
[issue]: https://github.com/epfl-idevelop/wpforms-epfl-payonline/issues
[WPForms]: https://wpforms.com/
[Elite]: https://wpforms.com/checkout?edd_action=add_to_cart&download_id=290232&discount=SAVE50
[Pro]: https://wpforms.com/checkout?edd_action=add_to_cart&download_id=290008&discount=SAVE50