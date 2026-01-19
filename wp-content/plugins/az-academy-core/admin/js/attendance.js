(function ($) {
  $(function () {
    if (
      window.AZAC_Att &&
      typeof window.AZAC_Att.fetchExisting ===
        "function"
    ) {
      window.AZAC_Att.fetchExisting("check-in");
      window.AZAC_Att.fetchExisting(
        "mid-session"
      );
    }
    if (
      window.AZACU &&
      typeof window.AZACU.updateSessionTitle ===
        "function"
    ) {
      window.AZACU.updateSessionTitle(
        window.azacData.sessionDate ||
          window.azacData.today
      );
    }
  });
})(jQuery);
