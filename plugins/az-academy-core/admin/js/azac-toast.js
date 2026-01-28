console.log("Toast Library Loaded");
(function () {
  window.showAzacToast = function (
    message,
    type,
  ) {
    var container = document.querySelector(
      ".azac-toast-container",
    );
    if (!container) {
      container = document.createElement("div");
      container.className =
        "azac-toast-container";
      document.body.appendChild(container);
    }

    var toast = document.createElement("div");
    toast.className =
      "azac-toast-item " + (type || "info");
    toast.innerHTML = message;

    container.appendChild(toast);

    // Auto remove after 3 seconds
    setTimeout(function () {
      toast.classList.add("fade-out");
      toast.addEventListener(
        "animationend",
        function () {
          if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
          }
          if (
            container.childNodes.length === 0
          ) {
            if (container.parentNode) {
              container.parentNode.removeChild(
                container,
              );
            }
          }
        },
      );
    }, 3000);
  };
})();
