/*global VuFind, Splide, unwrapJQuery */
VuFind.register("carousels", function VuFindCarousels() {
  function setup(scope = document) {
    if (typeof Splide === "undefined") {
      return;
    }

    const config = {
      autoHeight: true,
      gap: "0.5em",
      perPage: 4,
      classes: {
        // Add classes for pagination.
        pagination: "splide__pagination carousel-pagination", // container
        page: "splide__pagination__page carousel-indicator", // each button
      }
    };

    unwrapJQuery(scope)
      .querySelectorAll("[data-carousel]")
      .forEach((carousel) => {
        if (carousel.classList.contains("is-initialized")) {
          return;
        }

        new Splide(carousel, config).mount();
      });
  }

  function init() {
    setup();
  }

  return { init, setup };
});
