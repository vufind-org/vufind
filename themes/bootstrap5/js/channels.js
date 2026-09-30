/*global getUrlRoot, VuFind */
VuFind.register("channels", function Channels() {
  /**
   * Retrieve a specific item's element from within a channel.
   * @param {string} channelID Channel to search
   * @param {string} source Record source
   * @param {string} id Record ID
   * @returns {HTMLElement|null} Channel item matching the record source and ID
   */
  function findChannelItem(channelID, source, id) {
    const channel = document.getElementById(channelID);
    // Avoid bad selectors with sources or ids
    for (const item of channel.querySelectorAll(".channel-item")) {
      if (
        item.dataset.recordId === id &&
        item.dataset.recordSource === source
      ) {
        return item;
      }
    }
    return null;
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
    for (let len = words.length; len--; ) {
      el.textContent = `${words.slice(0, len).join(" ")}${VuFind.translate("eol_ellipsis")}`;
      if (currHeight > el.offsetHeight) {
        currHeight = el.offsetHeight;
        strings.unshift(el.textContent);
      }
    }

    el.textContent = strings[Math.min(strings.length, targetLines) - 1];
  }

  /**
   * Add an aria-polite message to a channel
   * @param {HTMLElement} channel Target channel
   * @param {Array<Node>} newChildren Elements and strings to populate message
   */
  function ariaAnnounce(channel, newChildren) {
    const messageEl = channel.querySelector(".gallery-polite-alert");
    messageEl.replaceChildren(...newChildren);
  }

  /**
   * Format an aria-polite message from a new channel
   * @param {HTMLElement} channel Target channel
   * @param {HTMLElement} newChannel New channel
   */
  function ariaAnnounceNewChannel(channel, newChannel) {
    // Make link
    const newChannelLink = document.createElement("a");
    newChannelLink.textContent = VuFind.translate("channel_new_channel_aria_link");
    newChannelLink.setAttribute("href", `#${newChannel.id}`);

    // Announce
    ariaAnnounce(channel, [
      VuFind.translate("channel_new_channel_aria_message"),
      " ", // space before link
      newChannelLink
    ]);
  }

  /**
   * Format an aria-polite message from a new channel
   * @param {HTMLElement} firstNewItem First new item
   * @param {number} count Number of added items
   */
  function ariaAnnounceNewItems(firstNewItem, count) {
    const channel = firstNewItem.closest(".channel");

    // Make link
    const firstNewItemLink = document.createElement("a");
    firstNewItemLink.textContent = VuFind.translate("channel_more_items_aria_link");
    firstNewItemLink.setAttribute("href", `#${firstNewItem.id}`);

    // Announce
    ariaAnnounce(channel, [
      VuFind.translate("channel_more_items_aria_message", { "%%count%%": count }),
      " ", // space before link
      firstNewItemLink
    ]);
  }

  /**
   * @param {HTMLElement} channelEl containing div
   * @returns {NodeList<HTMLElement>} channel items that are hidden
   */
  function getHiddenItems(channelEl) {
    return channelEl.querySelectorAll(".hidden-batch-item");
  }

  /**
   * Helper function to disable the Load more items button
   * @param {HTMLButtonElement} loadMoreBtn The button
   * @returns {void}
   */
  function disableLoadMoreBtn(loadMoreBtn) {
    // Disable
    loadMoreBtn.classList.add("disabled");
    loadMoreBtn.setAttribute("aria-disabled", "true");
    // Store label for later
    loadMoreBtn.setAttribute("data-enabled-label", loadMoreBtn.getAttribute("aria-label"));
    // Change content
    loadMoreBtn.textContent = VuFind.translate("loading_ellipsis");
    loadMoreBtn.setAttribute("aria-label", VuFind.translate("loading_ellipsis"));
  }

  /**
   * Helper function to enable the Load more items button
   * @param {HTMLButtonElement} loadMoreBtn The button
   * @returns {void}
   */
  function enableLoadMoreBtn(loadMoreBtn) {
    // Disable
    loadMoreBtn.classList.remove("disabled");
    loadMoreBtn.removeAttribute("aria-disabled");
    // Change content
    loadMoreBtn.textContent = VuFind.translate("channel_more_items");
    loadMoreBtn.setAttribute(
      "aria-label",
      loadMoreBtn.hasAttribute("data-enabled-label")
        ? loadMoreBtn.getAttribute("data-enabled-label")
        : VuFind.translate("channel_more_items")
    );
  }

  /**
   * Helper function to visually hide and disable the more items button
   * @param {HTMLButtonElement} loadMoreBtn The button
   * @returns {void}
   */
  function hideLoadMoreBtn(loadMoreBtn) {
    // Screen-reader disable
    loadMoreBtn.classList.add("disabled");
    loadMoreBtn.setAttribute("aria-disabled", "true");
    // Visually hide
    loadMoreBtn.classList.add("visually-hidden");
  }

  /**
   * AJAX load more records
   * should fire when we have less than one page of hidden items
   * @param {HTMLButtonElement} loadMoreBtn clicked button
   * @param {HTMLElement} channelEl div container
   * @returns {Promise<void>}
   */
  function requestMoreItems(loadMoreBtn, channelEl) {
    const url = new URL(decodeURIComponent(loadMoreBtn.dataset.href), location.origin);
    return fetch(url.toString() + "&layout=lightbox")
      .then((res) => res.text())
      .then((resHTML) => {
        // Extract channel items
        const parser = new DOMParser();
        const resDom = parser.parseFromString(resHTML, "text/html");

        const firstChannel = resDom.querySelector(".channel");
        const records = firstChannel
          ? firstChannel.querySelectorAll(".channel-item")
          : [];

        // Add new records (hidden)
        const targetList = channelEl.querySelector(".channel-list");
        const channelID = targetList.closest(".channel").getAttribute("id");
        const index = channelEl.shownItems + channelEl.hiddenItems;
        for (let i = 0; i < records.length; i++) {
          const record = records[i];

          // Update information
          record.id = `${channelID}-item-${String(index + i).padStart(3, "0")}`;

          // Append
          targetList.append(record);

          // Show
          record.classList.add("hidden-batch-item");
          record.classList.remove("hidden");

          clampLines(record.querySelector(".channel-item-title"));
        }

        channelEl.hiddenItems += records.length;

        // Mark that we've loaded all records
        if (records.length < Number(channelEl.dataset.pageSize)) {
          channelEl.ajaxAvailable = false;
        }

        // Set button to next page
        url.searchParams.set("page", Number(url.searchParams.get("page")) + 1);
        loadMoreBtn.setAttribute("data-href", url.toString());
      });
  }

  /**
   * @param {HTMLElement} channelEl containing div
   * @returns {void}
   */
  function showMoreItems(channelEl) {
    const loadMoreBtn = channelEl.querySelector(".channel-load-more-btn");
    if (loadMoreBtn.classList.contains("disabled")) {
      return;
    }

    // Disable more paging
    disableLoadMoreBtn(loadMoreBtn);

    // Reveal hidden items
    const pageSize = Number(channelEl.dataset.pageSize);
    let hiddenItems = getHiddenItems(channelEl);

    // Reveal hidden items (limit to pageSize)
    const revealCount = Math.min(pageSize, hiddenItems.length);
    for (let i = 0; i < revealCount; i++) {
      hiddenItems[i].classList.remove("hidden-batch-item");
      clampLines(hiddenItems[i].querySelector(".channel-item-title"));
    }

    channelEl.shownItems += revealCount;
    channelEl.hiddenItems -= revealCount;

    if (hiddenItems.length > 0) {
      ariaAnnounceNewItems(hiddenItems[0], revealCount);
    }

    // Do we need more items?
    // @TODO: replace with await when available
    const promise = channelEl.hiddenItems < pageSize && channelEl.ajaxAvailable
      ? requestMoreItems(loadMoreBtn, channelEl)
      : Promise.resolve(); // no load needed

    // Update button after load
    promise.finally(() => {
      if (channelEl.hiddenItems === 0 && !channelEl.ajaxAvailable) {
        hideLoadMoreBtn(loadMoreBtn);
      } else {
        enableLoadMoreBtn(loadMoreBtn);
      }
    });
  }

  /**
   * @param {HTMLElement} channelEl div container
   * @returns {void}
   */
  function channelInit(channelEl) {
    // Clamp titles to 3 lines
    for (const title of channelEl.querySelectorAll(".channel-item-title")) {
      clampLines(title);
    }

    // Establish forward AJAX
    const allItems = channelEl.querySelectorAll(".channel-item");
    const hiddenItems = getHiddenItems(channelEl);
    const pageSize = Number(channelEl.dataset.pageSize);

    channelEl.shownItems = allItems.length - hiddenItems.length;
    channelEl.hiddenItems = hiddenItems.length;

    const loadMoreBtn = channelEl.querySelector(".channel-load-more-btn");
    channelEl.ajaxAvailable = channelEl.shownItems >= pageSize;

    // Less items than batchSize
    if (channelEl.shownItems < pageSize) {
      hideLoadMoreBtn(loadMoreBtn);
    }

    // Less hidden items than needed for a second page
    if (channelEl.ajaxAvailable && channelEl.hiddenItems < pageSize) {
      requestMoreItems(loadMoreBtn, channelEl);
    }
  }

  /**
   * @param {HTMLElement} record Channel item to preview
   * @param {string} channelID record's channel id (hard to get from quicklook)
   * @param {string} htmlContent HTML content to display (record metadata)
   * @returns {string} HTML of quick look controls
   */
  function formatQuickLook(record, channelID, htmlContent) {
    const template = document.getElementById("template-channels-quick-look");
    const content = template.content.cloneNode(true).children[0];

    // Set title
    const titleLink = record.querySelector(".channel-item-title");
    const qlTitleEl = content.querySelector(".ql-title");
    if (titleLink.title) {
      qlTitleEl.textContent = titleLink.title;
      qlTitleEl.setAttribute("title", titleLink.title);
    } else {
      qlTitleEl.textContent = titleLink.textContent;
      qlTitleEl.removeAttribute("title");
    }

    // Update View Record link
    content
      .querySelector(".ql-view-record-btn")
      .setAttribute("href", titleLink.getAttribute("href"));

    // Set data for prev and next buttons
    const id = record.dataset.recordId;
    const source = record.dataset.recordSource;
    content.setAttribute("data-channel-id", channelID);
    content.setAttribute("data-record-id", id);
    content.setAttribute("data-record-source", source);

    // Set URL for Explore related channels button
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

    const parser = new DOMParser();
    const contentDoc = parser.parseFromString(htmlContent, "text/html");
    content.append(contentDoc.body.firstChild);

    return content.outerHTML;
  }

  /**
   * @param {HTMLElement} record Channel item to preview
   * @param {string | null} _channelID record's channel id (hard to get from quicklook)
   * @returns {void}
   */
  function quickLook(record, _channelID = null) {
    const channelID =
      _channelID === null
        ? record.closest(".channel").getAttribute("id")
        : _channelID;

    // Load more options if we're past the end of what's available (e.g. due to user hitting "Next")
    if (record.classList.contains("hidden-batch-item")) {
      showMoreItems(document.getElementById(channelID));
    }

    const titleLink = record.querySelector(".channel-item-title");
    const href = titleLink.getAttribute("href");

    VuFind.lightbox.render(VuFind.loading());

    const formData = new FormData();
    formData.append("tab", "description");

    fetch(VuFind.path + getUrlRoot(href) + "/AjaxTab", {
      method: "POST",
      body: formData,
    })
      .then((res) => res.text())
      .then(function quickLookFetchDone(htmlContent) {
        VuFind.lightbox.render(formatQuickLook(record, channelID, htmlContent));
      });
  }

  /**
   * @param {HTMLAnchorElement} link .channel-add-link
   * @returns {void}
   */
  function addChannel(link) {
    let callerChannelEl = link.closest(".channel");
    // Remove from dropdowns
    const group = link.closest(".channel-add-menu").dataset.group;
    const token = link.dataset.token;
    const relatedMenus = Array.from(
      document.querySelectorAll(`.channel-add-menu[data-group="${group}"]`)
    );
    for (const menu of relatedMenus) {
      // Remove add links for this channel
      const usedMenuItem = menu.querySelector(`[data-token="${token}"]`);
      if (usedMenuItem) {
        usedMenuItem.remove();
      }
      // Remove empty menus
      if (menu.querySelector(".channel-add-link") === null) {
        menu.remove();
      }
    }

    // Get and parse results
    let ariaAnnounced = false;
    fetch(link.getAttribute("href"))
      .then(function addChannelResponse(res) {
        return res.text();
      })
      .then(function addChannelParseHTML(resHTML) {
        const parser = new DOMParser();
        const resDOM = parser.parseFromString(resHTML, "text/html");

        // Add channels to DOM
        for (const channelEl of resDOM.querySelectorAll(".channel")) {
          // Empty result
          if (channelEl.querySelectorAll(".channel-item").length === 0) {
            const title = channelEl.querySelector("h2");
            const emptyWrapper = parser.parseFromString(
              `<div class="channel">
                <div class="channel-title">
                  <h2>${title.innerHTML}</h2>
                </div>
                <div class="channel-content">
                  ${VuFind.transEsc("nohit_heading")}
                </div>
              </div>`,
              "text/html"
            );

            callerChannelEl.after(emptyWrapper.firstChild);
            continue;
          }

          // Add related channels menu
          const relatedMenu = document.querySelector(`.channel-add-menu[data-group="${group}"]`);
          if (relatedMenu) {
            channelEl
              .querySelector(".channel-title")
              .after(relatedMenu.cloneNode(true));
          }

          // Add channel
          callerChannelEl.after(channelEl);
          channelInit(channelEl);

          // Announce to screen readers
          if (!ariaAnnounced) {
            ariaAnnounceNewChannel(callerChannelEl, channelEl);
            ariaAnnounced = true;
          }
        }
      });
  }

  /**
   * Setup the channels module and events
   */
  function init() {
    // Initial manipulations
    for (const channelEl of document.querySelectorAll(".channel")) {
      channelInit(channelEl);
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
        showMoreItems(event.target.closest(".channel"));
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
