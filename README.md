# WireMailPostmark

This is a module by Netcarver and Pete of Nifty Solutions for ProcessWire CMS that allows outbound transactional email
to be sent via the Postmark service at postmarkapp.com.

This requires PHP7.2+, a recent copy of ProcessWire 3 and a Postmark account.

Make sure you have added a verified sender signature to your Postmark account and configured your first server. You'll
need both your sender signature email address and your server's API token in order to use this module.

Install the module as usual, then insert your Postmark Server Token and Sender Signature into the module settings and
save the settings. If all goes well, you'll see the Postmark service status and the stats for your server after a few
seconds.

![Module Settings Page Layout](./docs/screenshot-module-config.png)

If you get accurate stats shown in the module configuration page, you should then be able to send emails using this
module.

## Postmark

![Postmark's Server Status Page](./docs/screenshot-postmark-status.png)

If this module proves to be of any use to you and you end up signing up for a paid plan with Postmark, please consider
contacting Postmark's excellent support service regarding their [Friends-and-Family
program](https://postmarkapp.com/loves/friends-family), letting them know about Nifty Solutions and we'll both get a
month's free service.
