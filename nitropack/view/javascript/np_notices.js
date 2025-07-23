(function () {
  function updateNotificationCount() {
    let notificationsCount = null;
    let notificationsCountContainer =
      document.getElementsByClassName("is-dismissible");

    if (notificationsCountContainer)
      notificationsCount = notificationsCountContainer.length;

    /* Admin bar update - NitroPack menu */
    let totalIssues = document.getElementById("nitro-total-issues-count");
    totalIssues.innerHTML = parseInt(totalIssues.innerHTML) - 1;
    if (parseInt(totalIssues.innerHTML) === 0) totalIssues.remove();

    /* settings sub menu count update */
    let notificationIssues = document.getElementById(
      "nitro-notification-issues-count"
    );
    notificationIssues.innerHTML = parseInt(notificationIssues.innerHTML) - 1;
    if (parseInt(notificationIssues.innerHTML) === 0)
      notificationIssues.remove();
  }

  /* Dismiss by setting a transient - used for notifications from the app */
  document.addEventListener(
    "click",
    function (e) {
      if (e.target.matches(".dismiss-by-transient .btn-dismiss")) {
        const xhr = new XMLHttpRequest();
        const data = new FormData();
        data.append("action", "nitropack_dismiss_notification_by_transient");
        data.append("nonce", nitropack_notices_vars.nonce);
        data.append("notification_id", e.target.dataset.notification_id);
        data.append("notification_end", e.target.dataset.notification_end);

        xhr.onreadystatechange = function () {
          if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
              let response = JSON.parse(xhr.responseText);
              if (response.transient_status === true) {
                let notificationElement = e.target.closest(
                  ".nitro-notification"
                );

                if (notificationElement) notificationElement.remove();

                updateNotificationCount();
              }
            } else {
              console.log("Error: " + xhr.status);
            }
          }
        };
        xhr.open("POST", ajaxurl);
        xhr.send(data);
      }
    },
    true
  );
  /* Dismiss permanently notification by updating wp_options */
  document.addEventListener(
    "click",
    function (e) {
      if (e.target.matches(".dismiss-by-option .btn-dismiss")) {
        const dismissButton = e.target;
        const dismissId = dismissButton.dataset.dismissibleId;
        if (!dismissId) return;

        const noticeElement = dismissButton.closest(".nitro-notification");
        const data = new FormData();
        data.append("action", "nitropack_dismiss_permanently_notification");
        data.append("nonce", nitropack_notices_vars.nonce);
        data.append("notification_id", dismissId);

        const xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function () {
          if (xhr.readyState === XMLHttpRequest.DONE) {
            try {
              const response = JSON.parse(xhr.responseText);
              if (xhr.status === 200 && response.success) {
                noticeElement?.remove();
              } else {
                console.error(
                  "Dismiss failed:",
                  response?.data?.message || "Unknown error"
                );
              }
            } catch (err) {
              console.error("Failed to parse response:", err);
            }
          }
        };

        xhr.open("POST", ajaxurl);
        xhr.send(data);
      }
    },
    true
  );
})();
