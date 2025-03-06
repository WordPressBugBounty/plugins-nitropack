(function () {
  /* Dismiss notification by cookie */
  document.addEventListener(
    "click",
    (event) => {
      const dismissButton = event.target.closest(
        ".nitro-notification.is-dismissible[data-dismissible-id] .btn-dismiss"
      );

      if (dismissButton) {
        const noticeElement = dismissButton.closest(
          ".nitro-notification.is-dismissible[data-dismissible-id]"
        );

        if (noticeElement) {
          const noticeId = noticeElement.dataset.dismissibleId;

          if (noticeId) {
            document.cookie = `dismissed_notice_${noticeId}=1; path=/; max-age=${
              86400 * 30
            }; secure`;
          }
        }
      }
    },
    true
  );

  /* Dismiss by setting a transient - used for notifications from the app */
  document.addEventListener(
    "click",
    function (e) {
      if (e.target.matches(".app-notification .btn-dismiss")) {
        var xhr = new XMLHttpRequest();
        var data = new FormData();
        data.append("action", "nitropack_dismiss_notification");
        data.append("nonce", nitropack_notices_vars.nonce);
        data.append("notification_id", e.target.dataset.notification_id);
        data.append("notification_end", e.target.dataset.notification_end);

        xhr.onreadystatechange = function () {
          if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
              let response = JSON.parse(xhr.responseText);
              if (response.transient_status === true) {
                let notificationsCount = null;
                let notificationElement = e.target.closest(
                  ".nitro-notification"
                );

                if (notificationElement) notificationElement.remove();

                let notificationsCountContainer =
                  document.getElementById("app-notifications");
                if (notificationsCountContainer)
                  notificationsCount =
                    notificationsCountContainer.querySelectorAll("li").length;

                if (notificationsCount === 0)
                  document.getElementById("app-notifications").remove();

                /* Admin bar update - NitroPack menu */
                let totalIssues = document.getElementById(
                  "nitro-total-issues-count"
                );
                totalIssues.innerHTML = parseInt(totalIssues.innerHTML) - 1;
                if (parseInt(totalIssues.innerHTML) === 0) totalIssues.remove();

                /* settings sub menu count update */
                let notificationIssues = document.getElementById(
                  "nitro-notification-issues-count"
                );
                notificationIssues.innerHTML =
                  parseInt(notificationIssues.innerHTML) - 1;
                if (parseInt(notificationIssues.innerHTML) === 0)
                  notificationIssues.remove();
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
})();
