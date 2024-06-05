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
      breakpoints: { 640: { perPage: 3 }},
    };

    unwrapJQuery(scope)
      .querySelectorAll("[data-vf-carousel]")
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
