/*global getUrlRoot, VuFind */
VuFind.register("channels", function Channels() {
  /**
   * Retrieve a specific item's element from within a channel.
   * @param {string} channelID Channel to search
   * @param {string} source Record source
   * @param {string} id Record ID
   * @returns {HTMLElement} Channel item matching the record source and ID
   */
  function findChannelItem(channelID, source, id) {
    return document.querySelector(
      `#${channelID} [data-record-source="${source}"][data-record-id="${id}"]`
    );
  }

  /**
   * Truncate lines to a number of lines. Truncated text will end in ellipses.
   *
   * Works by removing words and saving the string every time the element shrinks.
   * This results in a list of strings by number of lines (one-indexed).
   * We then select the appropriate string for our target (or the last if less).
   * @param {HTMLElement} el Target element
   * @param {number} targetLines Maximum number of lines
   */
  function clampLines(el, targetLines = 3) {
    const strings = [el.textContent];

    let currHeight = el.offsetHeight;
    const words = el.textContent.split(" ");
    for (let len = words.length; len--;) {
      el.textContent = `${words.slice(0, len).join(" ")}${VuFind.translate("eol_ellipsis")}`;
      if (currHeight > el.offsetHeight) {
        currHeight = el.offsetHeight;
        strings.unshift(el.textContent);
      }
    }

    el.textContent = strings[Math.min(strings.length, targetLines) - 1];
  }

  /**
   * @param {HTMLElement} link .channel-add-link
   * @returns {void}
   */
  function addChannel(link) {
    let callerChannelEl = link.closest(".channel");
    // Remove from dropdowns
    const group = link.closest(".channel-add-menu").dataset.group;
    const token = link.dataset.token;
    let groupMenus = Array.from(document.querySelectorAll(`[data-group="${group}"]`));
    for (const menu of groupMenus) {
      const doomed = menu.querySelector(`[data-token="${token}"]`);
      if (doomed) {
        doomed.remove();
      }
      if (menu.querySelector(".channel-add-link") === null) {
        for (const emptyMenu of groupMenus) {
          emptyMenu.remove();
        }
        groupMenus = null;
        break;
      }
    }

    // Get and parse results
    fetch(link.getAttribute("href"))
      .then(function addChannelResponse(res) {
        return res.text();
      })
      .then(function addChannelParseHTML(resHTML) {
        const parser = new DOMParser();
        const resDOM = parser.parseFromString(resHTML, "text/html");

        // Add channels to DOM
        for (const channelEl of resDOM.querySelectorAll(".channel")) {
          // Make sure the channel has content
          if (channelEl.querySelectorAll(".channel-item").length > 0) {
            // Add related channels menu
            if (groupMenus) {
              channelEl.querySelector(".channel-title").after(groupMenus[0].cloneNode(true));
            }
            // Add channel
            callerChannelEl.after(channelEl);
            continue;
          }

          // Empty result
          const title = channelEl.querySelector("h2");
          const emptyWrapper = parser.parseFromString(
            `<div class="channel">
              <div class="channel-title">
                <h2>${title.innerHTML}</h2>
              </div>
              <div class="channel-content">
                ${VuFind.translate('nohit_heading')}
              </div>
            </div>`,
            "text/html"
          );

          callerChannelEl.after(emptyWrapper.firstChild);
          callerChannelEl = emptyWrapper.firstChild;
        }
      });
  }

  /**
   * Helper function to enable the Load more items button
   * @param {HTMLButtonElement} loadMoreBtn The button
   * @returns {void}
   */
  function enableLoadMoreBtn(loadMoreBtn) {
    loadMoreBtn.classList.remove("disabled");
    loadMoreBtn.removeAttribute("aria-disabled");
  }

  /**
   * Helper function to disable the Load more items button
   * @param {HTMLButtonElement} loadMoreBtn The button
   * @returns {void}
   */
  function disableLoadMoreBtn(loadMoreBtn) {
    loadMoreBtn.classList.add("disabled");
    loadMoreBtn.setAttribute("aria-disabled", 1);
  }

  /**
   * Helper function to visually hide and disable the more items button
   * @param {HTMLButtonElement} loadMoreBtn The button
   * @returns {void}
   */
  function hideLoadMoreBtn(loadMoreBtn) {
    // visually hide
    loadMoreBtn.classList.add("visually-hidden");
    // screen-reader disable
    loadMoreBtn.classList.add("disabled");
    loadMoreBtn.setAttribute("aria-disabled", 1);
  }

  /**
   * @param {Event} event Click event from .channel-load-more-btn
   * @returns {void}
   */
  function loadMoreItems(event) {
    const btn = event.target;
    if (btn.classList.contains("disabled")) {
      return false;
    }

    // Reveal hidden items
    const targetChannel = btn.closest(".channel");
    const pageSize = Number(targetChannel.dataset.pageSize);
    const hiddenItems = targetChannel.querySelectorAll(".hidden-batch-item");

    // Reveal hidden items (limit to pageSize)
    hiddenItems.forEach((item, index) => {
      if (index < pageSize) {
        item.classList.remove("hidden-batch-item");
      }
    });

    // Out of records
    if (hiddenItems.length < Number(targetChannel.dataset.rowSize)) {
      hideLoadMoreBtn(btn);
      return;
    }

    // How many more records do we need?
    const neededCount = pageSize - hiddenItems.length;
    if (neededCount <= 0) {
      return; // skip loading more records
    }

    // AJAX load more records
    const url = new URL(decodeURIComponent(btn.dataset.href), location.origin);
    btn.textContent = VuFind.translate("loading_ellipsis");
    disableLoadMoreBtn(btn);

    fetch(url.toString() + "&layout=lightbox")
      .then((res) => res.text())
      .then(function loadMoreItemsParseHTML(resHTML) {
        const parser = new DOMParser();
        const resDom = parser.parseFromString(resHTML, "text/html");

        const firstChannel = resDom.querySelector(".channel");
        const records = firstChannel
          ? firstChannel.querySelectorAll(".channel-item")
          : [];

        const targetList = targetChannel.querySelector(".channel-list");
        for (let i = 0; i < records.length; i++) {
          const record = records[i];
          record.classList.remove("hidden");
          if (i >= neededCount) {
            record.classList.add("hidden-batch-item");
          }
          targetList.append(record);
          clampLines(record.querySelector(".channel-item-title"));
        }

        // Hide button
        if (records.length < Number(targetChannel.dataset.batchSize)) {
          hideLoadMoreBtn(btn);
        }
      });

    // Set button to next, next page
    url.searchParams.set("page", Number(url.searchParams.get("page")) + 1);
    btn.setAttribute("data-href", url.toString());
    btn.textContent = VuFind.translate("channel_more_items");
    enableLoadMoreBtn(btn);
  }

  /**
   * @param {HTMLElement} record Channel item to preview
   * @param {string} channelID record's channel id (hard to get from quicklook)
   * @returns {string} HTML of quick look controls
   */
  function quickLookHeader(record, channelID) {
    const template = document.getElementById("template-channels-quick-look");
    const content = template.content.cloneNode(true).children[0];

    const titleLink = record.querySelector(".channel-item-title");
    content.querySelector(".ql-title").textContent = titleLink.textContent;
    content
      .querySelector(".ql-view-record-btn")
      .setAttribute("href", titleLink.getAttribute("href"));

    const id = record.dataset.recordId;
    const source = record.dataset.recordSource;
    content.setAttribute("data-channel-id", channelID);
    content.setAttribute("data-record-id", id);
    content.setAttribute("data-record-source", source);

    // Set URL for quicklook
    const expandParams = new URLSearchParams({ id, source }); // escape
    content
      .querySelector(".ql-expand-btn")
      .setAttribute("href", `${VuFind.path}/Channels/Record?${expandParams}`);

    const prevBtn = content.querySelector(".ql-prev-item-btn");
    if (record.previousElementSibling) {
      prevBtn.classList.remove("disabled");
      prevBtn.removeAttribute("disabled");
    } else {
      prevBtn.classList.add("disabled");
      prevBtn.setAttribute("disabled", "");
    }

    const nextBtn = content.querySelector(".ql-next-item-btn");
    if (record.nextElementSibling) {
      nextBtn.classList.remove("disabled");
      nextBtn.removeAttribute("disabled");
    } else {
      nextBtn.classList.add("disabled");
      nextBtn.setAttribute("disabled", "");
    }

    return content.outerHTML;
  }

  /**
   * @param {HTMLElement} record Channel item to preview
   * @param {string | null} _channelID record's channel id (hard to get from quicklook)
   * @returns {void}
   */
  function quickLook(record, _channelID = null) {
    const titleLink = record.querySelector(".channel-item-title");
    const href = titleLink.getAttribute("href");

    VuFind.lightbox.render(VuFind.loading());

    const formData = new FormData();
    formData.append("tab", "description");

    const channelID = _channelID === null
      ? record.closest(".channel-list").getAttribute("id")
      : _channelID;
    fetch(VuFind.path + getUrlRoot(href) + "/AjaxTab", {
      method: "POST",
      body: formData,
    })
      .then((res) => res.text())
      .then(function quickLookFetchDone(htmlContent) {
        VuFind.lightbox.render(`${quickLookHeader(record, channelID)} ${htmlContent}`);
      });
  }

  /**
   * Setup the channels module and events
   */
  function init() {
    // Initial manipulations
    for (const channelEl of document.querySelectorAll(".channel")) {
      // Disable the load more button is there are less items than the batchSize
      const allItems = channelEl.querySelectorAll(".channel-item");
      if (allItems.length < Number(channelEl.dataset.rowSize)) {
        hideLoadMoreBtn(channelEl.querySelector(".channel-load-more-btn"));
      }

      // Clamp titles to 3 lines
      for (const title of channelEl.querySelectorAll(".channel-item-title")) {
        clampLines(title);
      }
    }

    // Global button listener
    document.addEventListener("click", function channelsClickHandler(event) {
      // More channels dropdown links
      if (event.target.closest(".channel-add-link")) {
        addChannel(event.target.closest(".channel-add-link"));
        event.preventDefault();
        return false;
      }

      // More channels button (first two dropdown links)
      if (event.target.closest(".channel-add-more-btn")) {
        const addLinks = Array.from(
          event.target
            .closest(".channel-add-menu")
            .querySelectorAll(".channel-add-link")
        );
        for (let i = 0; i < Math.min(2, addLinks.length); i++) {
          addChannel(addLinks[i]);
        }
        event.preventDefault();
        return false;
      }

      // Show More buttons
      if (event.target.closest(".channel-load-more-btn")) {
        loadMoreItems(event);
        event.preventDefault();
        return false;
      }

      // Quick Look buttons
      if (event.target.closest(".channel-quick-look-btn")) {
        quickLook(
          event.target.closest(".channel-item"),
          event.target.closest(".channel-list").getAttribute("id")
        );
        event.preventDefault();
        return false;
      }

      // Prev Item buttons (in quick look)
      if (event.target.closest(".ql-prev-item-btn")) {
        const group = event.target.closest(".channels-quick-look");
        const record = findChannelItem(
          group.dataset.channelId,
          group.dataset.recordSource,
          group.dataset.recordId
        );
        if (record.previousElementSibling) {
          quickLook(record.previousElementSibling, group.dataset.channelId);
          event.preventDefault();
          return false;
        }
      }

      // Next Item buttons (in quick look)
      if (event.target.closest(".ql-next-item-btn")) {
        const group = event.target.closest(".channels-quick-look");
        const record = findChannelItem(
          group.dataset.channelId,
          group.dataset.recordSource,
          group.dataset.recordId
        );
        if (record.nextElementSibling) {
          quickLook(record.nextElementSibling, group.dataset.channelId);
          event.preventDefault();
          return false;
        }
      }
    });
  }

  return { init };
});
