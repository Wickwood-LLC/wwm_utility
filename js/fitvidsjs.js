/**
 * @file
 * Provides JavaScript additions for embedded videos
 */

(function ($, Drupal) {
  Drupal.behaviors.wwmVfpVideoWidth = {
    attach(context, settings) {
      // The Klaro module move src attribute value to data-src.
      $(".node").fitVids({ customSelector: 'iframe[data-src*="youtube.com"]'});
    }
  };
})(jQuery, Drupal);
