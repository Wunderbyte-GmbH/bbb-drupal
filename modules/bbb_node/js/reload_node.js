(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.bbbCheckStatusInit = {
    attach: function (context, settings) {
      window.parent.location.href = drupalSettings.bbb.reload.url;
    }
  };
})(jQuery, Drupal, drupalSettings);


