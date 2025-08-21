/*global getUrlRoot, VuFind */
VuFind.register("channels", function Channels() {
  function init() {
    document.addEventListener("click", function channelsClickHandler(event) {
      // Add channel buttons
      if (event.target.closest(".channel-add-link")) {
        addChannel(event);
        event.preventDefault();
        return false;
      }
      if (event.target.closest(".channel-add-more-btn")) {
        const addLinks = Array.from(
          event.target
            .closest(".channel-add-menu")
            .querySelectorAll(".dropdown-item")
        );
        for (let i = 0; i < Math.min(2, addLinks.length); i++) {
          addLinks[i].click();
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
        quickLook(event.target.closest(".channel-item"));
        event.preventDefault();
        return false;
      }

      // Prev Item buttons (in quick look)
      if (event.target.closest(".ql-prev-item-btn")) {
        const group = event.target.closest(".channels-quick-look");
        const record = findChannelItem(
          group.dataset.recordSource,
          group.dataset.recordId
        );
        if (record.previousElementSibling) {
          quickLook(record.previousElementSibling);
          event.preventDefault();
          return false;
        }
      }

      // Next Item buttons (in quick look)
      if (event.target.closest(".ql-next-item-btn")) {
        const group = event.target.closest(".channels-quick-look");
        const record = findChannelItem(
          group.dataset.recordSource,
          group.dataset.recordId
        );
        if (record.nextElementSibling) {
          quickLook(record.nextElementSibling);
          event.preventDefault();
          return false;
        }
      }
    });

    // Bootstrap
    for (const dropdownToggleEl of document.querySelectorAll("[data-toggle]")) {
      dropdownToggleEl.setAttribute(
        "data-bs-toggle",
        dropdownToggleEl.getAttribute("data-toggle")
      );
    }

    for (const title of document.querySelectorAll(".channel-item-title")) {
      clampLines(title, 3);
    }
  }

  /**
   * @param {string} source Record source
   * @param {string} id Record ID
   * @returns {HTMLElement}
   */
  function findChannelItem(source, id) {
    return document.querySelector(
      `[data-record-source="${source}"][data-record-id="${id}"]`
    );
  }

  /**
   * @param {Event} event Click event from .channel-add-link
   */
  async function addChannel(event) {
    const link = event.target;

    // Get and parse results
    const res = await fetch(link.getAttribute("href"));
    const resHTML = await res.text();

    const parser = new DOMParser();
    const resDOM = parser.parseFromString(resHTML, "text/html");

    // Add channels to DOM
    let callerChannelEl = link.closest(".channel");
    for (const channelEl of resDOM.querySelectorAll(".channel")) {
      // Make sure the channel has content
      if (channelEl.querySelectorAll(".channel-item").length > 0) {
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

    // Remove dropdown link
    link.closest(".dropdown-menu").removeChild(link.closest("li"));
  }

  /**
   * @param {Event} event Click event from .channel-load-more-btn
   */
  async function loadMoreItems(event) {
    const btn = event.target;
    if (btn.classList.contains("disabled")) {
      return false;
    }

    // Set button to next, next page
    const url = new URL(btn.href);
    url.searchParams.set("page", Number(url.searchParams.get("page")) + 1);
    btn.setAttribute("href", url.toString());

    // Get and parse results
    const res = await fetch(btn.href + "&layout=lightbox");
    const resHTML = await res.text();

    const parser = new DOMParser();
    const resDom = parser.parseFromString(resHTML, "text/html");
    console.log(resDom);

    const records = resDom.querySelectorAll(".channel-item");
    const channelList = btn.closest(".channel").querySelector(".channel-list");
    for (const record of records) {
      record.classList.remove("hidden");
      channelList.append(record);
      clampLines(record.querySelector(".channel-item-title"), 3);
    }

    // Disable button
    if (records.length < 6) {
      btn.classList.add("disabled");
      btn.removeAttribute("href");
      btn.setAttribute("aria-disabled", true);
    }
  }

  /**
   * @param {HTMLElement} record Channel item to preview
   */
  function quickLook(record) {
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
        VuFind.lightbox.render(`${quickLookHeader(record)} ${htmlContent}`);
      });
  }

  /**
   * @param {HTMLElement} record Channel item to preview
   * @returns {string} HTML of quick look controls
   */
  function quickLookHeader(record) {
    const template = document.getElementById("template-channels-quick-look");
    const content = template.content.cloneNode(true).children[0];

    const titleLink = record.querySelector(".channel-item-title");
    content.querySelector(".ql-title").textContent = titleLink.textContent;
    content
      .querySelector(".ql-view-record-btn")
      .setAttribute("href", titleLink.getAttribute("href"));

    const id = record.dataset.recordId;
    const source = record.dataset.recordSource;
    content.setAttribute("data-record-id", id);
    content.setAttribute("data-record-source", source);
    content
      .querySelector(".ql-expand-btn")
      .setAttribute("href", `${VuFind.path}/Channels/Record?id=${id}&source=${source}`);

    const prevBtn = content.querySelector(".ql-prev-item-btn");
    if (record.previousElementSibling) {
      prevBtn.removeAttribute("disabled");
    } else {
      prevBtn.setAttribute("disabled", "");
    }

    const nextBtn = content.querySelector(".ql-next-item-btn");
    if (record.nextElementSibling) {
      nextBtn.removeAttribute("disabled");
    } else {
      nextBtn.setAttribute("disabled", "");
    }

    return content.outerHTML;
  }

  /**
   * Truncate lines to a number of lines. Truncated text will end in ellipses.
   *
   * Works by removing words and saving the string every time the element shrinks.
   *   This results in a list of strings by number of lines (one-indexed).
   *   We then select the appropriate string for our target (or the last if less).
   *
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

  return { init };
});
