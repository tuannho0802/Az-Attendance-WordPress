(function ($) {
  "use strict";

  var progress = 0;
  var $loader = $("#azac-global-loader");
  var $bar = $loader.find(
    ".azac-progress-fill",
  );
  var $percent = $loader.find(
    ".azac-progress-percent",
  );
  var interval;

  function startProgress() {
    interval = setInterval(function () {
      if (progress >= 90) {
        clearInterval(interval);
      } else {
        progress += Math.random() * 5;
        if (progress > 90) progress = 90;
        updateUI();
      }
    }, 100);
  }

  function updateUI() {
    $bar.css("width", progress + "%");
    $percent.text(Math.round(progress) + "%");
  }

  function finishLoading() {
    clearInterval(interval);
    progress = 100;
    updateUI();

    setTimeout(function () {
      $("body").addClass("azac-loaded");
      setTimeout(function () {
        $loader.remove();
      }, 500);
    }, 200);
  }

  startProgress();

  $(window).on("load", function () {
    // Reduced delay from 400ms to 200ms for snappier feel
    setTimeout(function () {
      finishLoading();
    }, 30);
  });

  // Safety timeout
  setTimeout(function () {
    if (!$("body").hasClass("azac-loaded")) {
      finishLoading();
    }
  }, 5000);
})(jQuery);
