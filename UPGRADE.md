# Upgrade from Notification Center 1.x to 2.x

* The built-in Postmark Gateway has been removed.
* The built-in Queue Gateway has been removed.
* Embedding images in e-mails is not supported anymore.
* The configurable flattening delimiter in the e-mail notification type has been removed in favor of
  a more general approach based on Twig. See README.
* The configurable template in the notification type has been removed in favor of a Twig based solution.
* The corresponding language does not need an exact match of the root page language settings
  anymore. It will try to fall back to the general locale first, before taking the one that is
  configured to be fallback. E.g. (`de_CH` first, then `de` and only then the fallback).