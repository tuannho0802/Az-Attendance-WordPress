(function () {
  function ensureContainer() {
    var container = document.querySelector(
      ".azac-toast-container",
    );
    if (!container) {
      container = document.createElement("div");
      container.className =
        "azac-toast-container";
      document.body.appendChild(container);
    }
    return container;
  }

  function renderToast(message, type) {
    var container = ensureContainer();
    var toast = document.createElement("div");
    toast.className =
      "azac-toast-item " + (type || "info");
    toast.innerHTML = message;
    container.appendChild(toast);
    setTimeout(function () {
      toast.classList.add("fade-out");
      toast.addEventListener(
        "animationend",
        function () {
          if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
          }
          if (
            container.childNodes.length === 0 &&
            container.parentNode
          ) {
            container.parentNode.removeChild(
              container,
            );
          }
        },
      );
    }, 3000);
  }

  function showAfterLoader(message, type) {
    var done = function () {
      renderToast(message, type);
    };
    if (
      document.body.classList.contains(
        "azac-loaded",
      ) ||
      !document.getElementById(
        "azac-global-loader",
      )
    ) {
      // Loader is gone or not present
      done();
    } else {
      var tries = 0;
      var iv = setInterval(function () {
        tries++;
        if (
          document.body.classList.contains(
            "azac-loaded",
          ) ||
          !document.getElementById(
            "azac-global-loader",
          ) ||
          tries > 50
        ) {
          clearInterval(iv);
          setTimeout(done, 50);
        }
      }, 50);
    }
  }

  window.azacToast = {
    show: function (message, type) {
      showAfterLoader(message, type);
    },
    success: function (message) {
      showAfterLoader(message, "success");
    },
    error: function (message) {
      showAfterLoader(message, "error");
    },
    info: function (message) {
      showAfterLoader(message, "info");
    },
  };

  // Backward compatibility
  window.showAzacToast = function (
    message,
    type,
  ) {
    showAfterLoader(message, type);
  };
})();
