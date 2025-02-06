/* global finna, VuFind */

class FinnaFeedElement extends HTMLElement {

  /**
   * Observed attributes
   * @returns {Array} Attributes which triggers change event when changed
   */
  static get observedAttributes() {
    return ['feed-id'];
  }

  /**
   * Get feed id
   * @returns {string} Feed id currently set
   */
  get feedId() {
    return this.getAttribute('feed-id') || '';
  }

  /**
   * Set feed id
   * @param {string} newValue Value to set
   */
  set feedId(newValue) {
    this.setAttribute('feed-id', newValue);
  }

  /**
   * Constructor
   */
  constructor() {
    super();
    this.isTouchDevice = finna.layout.isTouchDevice() ? 1 : 0;
    this.slideHeight = undefined;
    this.onFeedLoaded = undefined;
    this.cache = [];
  }

  /**
   * Calculate feed scroll speed for splide.
   * @param {number} scrollCnt   Amount of slides to scroll
   * @param {number} scrollSpeed Default scroll speed to multiply
   * @returns {number} Calculated scroll speed
   */
  calculateScrollSpeed(scrollCnt, scrollSpeed) {
    return scrollSpeed * Math.max(1, (scrollCnt / 5));
  }

  /**
   * Adjust titles. Useful when the screen size changes so the elements
   * look as they should.
   * @param {object} settings Carousel settings
   */
  setTitleBottom(settings) {
    // Move title field below image
    let maxH = 0;
    this.querySelectorAll('.carousel-slide-header > span').forEach(el => {
      el.classList.add('title-bottom');
      maxH = Math.max(maxH, el.getBoundingClientRect().height);
    });
    this.querySelectorAll('.carousel-slide-header > span').forEach(el => {
      el.style.minHeight = el.style.height = `${maxH}px`;
    });
    this.querySelectorAll('.carousel-feed .carousel-text').forEach(el => {
      const textElement = el.querySelector('div.text span');
      if (!textElement) {
        return;
      }
      if (textElement.innerHTML.trim() !== '') {
        el.classList.add('text-bottom');
        el.style.maxHeight = `${settings.height}px`;
      }
    });
    settings.height = +settings.height + maxH;
  }

  /**
   * Create autoplay button.
   */
  createAutoplayButton() {
    const autoPlayButton = document.createElement('button');
    autoPlayButton.className = 'splide__toggle autoplay-button';
    autoPlayButton.type = 'button';

    const playSpan = document.createElement('span');
    playSpan.className = 'sr-only';
    playSpan.innerHTML = VuFind.translate('Carousel::Start Autoplay');
    const playIcon = document.createElement('span');
    playIcon.className = 'splide__toggle__play';
    playIcon.innerHTML = VuFind.icon('feed-play', 'play-icon');

    const pauseSpan = document.createElement('span');
    pauseSpan.className = 'sr-only';
    pauseSpan.innerHTML = VuFind.translate('Carousel::Stop Autoplay');
    const pauseIcon = document.createElement('span');
    pauseIcon.className = 'splide__toggle__pause';
    pauseIcon.innerHTML = VuFind.icon('feed-pause', 'pause-icon');

    autoPlayButton.append(playIcon, pauseIcon);
    this.querySelector('.carousel-autoplay').append(autoPlayButton);
  }

  /**
   * Add proper classes for arrow buttons.
   * @param {boolean} vertical Is the carousel vertical?
   */
  adjustArrowButtons(vertical) {
    const prev = this.querySelector('.splide__arrow--prev');
    if (prev) {
      prev.classList.add(vertical ? 'up' : 'left');
    }
    const next = this.querySelector('.splide__arrow--next');
    if (next) {
      next.classList.add(vertical ? 'down' : 'right');
    }
  }

  /**
   * When the feed is loaded or found from the internal cache.
   * Constructs the feed into the dom.
   * @param {object} jsonResponse The response obtained from the backend.
   */
  buildFeedDom(jsonResponse) {
    if (jsonResponse.data) {
      this.innerHTML = VuFind.updateCspNonce(jsonResponse.data.html);
      // Copy object so the reference to original is broken
      var settings = Object.assign({}, jsonResponse.data.settings);
      settings.height = settings.height || 300;
      const type = settings.type;
      const titleElement = this.parentElement.querySelector('.carousel-header');
      const carousel = ['carousel', 'carousel-vertical', 'slider'].includes(type);
      const hasContent = this.querySelector('.list-feed > ul > li, .carousel-feed > li, .feed-grid > div');
      if (!hasContent) {
        this.classList.add('hidden');
        if (titleElement) {
          titleElement.classList.add('hidden');
        }
        this.innerHTML = `<!-- No content received -->`;
        return;
      }
      if (carousel) {
        this.classList.add('splide');

        if (settings.autoplay && settings.autoplay > 0) {
          this.createAutoplayButton();
        }

        const vertical = 'carousel-vertical' === settings.type;
        const slider = 'slider' === settings.type;
        this.adjustArrowButtons(vertical);
        settings.vertical = vertical;
        this.splide = finna.carouselManager.createCarousel(this, settings);
        var titleBottom = typeof settings.titlePosition !== 'undefined' && settings.titlePosition === 'bottom';
        if (!vertical && !slider) {
          this.classList.add('carousel');
          if (titleBottom) {
            this.setTitleBottom(settings);
            this.querySelectorAll('.carousel-hover-title').forEach(el => {
              el.style.display = 'none';
            });
            this.querySelectorAll('.carousel-more.show-link').forEach(el => {
              el.style.display = 'none';
            });
            this.querySelectorAll('.carousel-hover-date').forEach(el => {
              el.style.display = 'none';
            });
            // Update the height of the splide component for title-bottom to display properly
            this.splide.options = {
              height: settings.height
            };
          } else if (!this.isTouchDevice) {
            this.querySelectorAll('.carousel-feed .carousel-text').forEach(el => {
              el.classList.remove('no-text');
            });
          }
        }
        if (slider) {
          this.classList.add('carousel-slider');
          if (settings.backgroundColor) {
            this.classList.add('slider-with-background');
            this.style.setProperty('--background-color', settings.backgroundColor);
          }
          if (settings.imagePlacement && settings.imagePlacement === 'right') {
            this.classList.add('image-right');
          }
          if (settings.stackedHeight) {
            this.style.setProperty('--height', `${settings.stackedHeight}px`);
          } else {
            this.style.setProperty('--height', `${settings.height}px`);
          }
          this.querySelectorAll('.slider-text-container').forEach(el => {
            if (el.clientHeight < el.scrollHeight) {
              el.classList.add('scrollable');
            } else {
              el.parentElement.style.alignItems = 'center';
            }
          });
        }

        // Text hover for touch devices
        if (!slider && finna.layout.isTouchDevice() && typeof settings.linkText === 'undefined') {
          this.querySelectorAll('.carousel-slide-more.carousel-show').forEach(el => {
            if (this.querySelector('.carousel-text:not(.no-text)') !== null) {
              el.classList.remove('hidden');
              el.parentNode.style.paddingRight = '30px';
            }
          });
          if (!settings.modal) {
            this.querySelectorAll('.carousel-text').forEach(el => {
              el.addEventListener('click', function doNothing(e) {
                e.stopImmediatePropagation();
              });
            });
          }
          this.querySelectorAll('.carousel-more').forEach(el => {
            if (el.classList.contains('carousel-close')) {
              el.classList.remove('hidden');
              el.querySelector('.js-carousel-close').addEventListener('click', function closeDescription(e) {
                e.stopImmediatePropagation();
                var slide = this.closest('.feed-item-holder');
                if (slide && slide.classList.contains('clicked')) {
                  slide.classList.remove('clicked');
                }
                e.preventDefault();
              });
            }
            if (el.classList.contains('show-link')) {
              el.classList.add('hidden');
            }
          });
          const onSlideClick = function onSlideClick (e) {
            e.stopImmediatePropagation();
            const slide = this.closest('.feed-item-holder');
            if (slide && !slide.classList.contains('clicked')) {
              this.querySelectorAll('.feed-item-holder.clicked').forEach(el => {
                el.classList.remove('.clicked');
              });
              slide.classList.add('clicked');
              e.preventDefault();
            }
          };
          this.querySelectorAll('.carousel-slide-more.carousel-show').forEach(el => {
            el.addEventListener('click', onSlideClick);
          });
        } else {
          this.querySelectorAll('.carousel').forEach(el => {
            el.classList.add('carousel-non-touch-device');
          });
        }

        var items = this.splide.length;
        var perPage = this.splide.options.perPage;
        if ( items <= perPage ) {
          this.splide.options = {
            pagination: false,
          };
        }
      }

      this.querySelectorAll('.carousel-text').forEach(el => {
        if (el.clientHeight < el.scrollHeight) {
          el.classList.add('scrollable');
        }
      });
      // Bind lightbox if feed content is shown in modal
      if (typeof settings.modal !== 'undefined' && settings.modal) {
        const onClickHolderLink = function onClickHolderLink() {
          const modal = document.getElementById('modal');
          if (modal) {
            modal.classList.add('feed-content');
          }
        };
        this.querySelectorAll('a').forEach(el => {
          el.addEventListener('click', onClickHolderLink);
        });
        VuFind.lightbox.bind(this);
      }
    }
    const truncatedGrid = this.querySelectorAll('.grid-item.truncate');
    if (truncatedGrid.length) {
      this.querySelectorAll('.show-more-feeds').forEach(el => {
        el.classList.remove('hidden');
      });
    }
    const showMoreFeeds = this.querySelector('.show-more-feeds');
    const showLessFeeds = this.querySelector('.show-less-feeds');
    if (showMoreFeeds) {
      showMoreFeeds.addEventListener('click', () => {
        truncatedGrid.forEach(el => {
          el.classList.remove('hidden');
        });
        showLessFeeds.classList.remove('hidden');
        showMoreFeeds.classList.add('hidden');
      });
    }
    if (showLessFeeds) {
      showLessFeeds.addEventListener('click', () => {
        truncatedGrid.forEach(el => {
          el.classList.add('hidden');
        });
        showMoreFeeds.classList.remove('hidden');
        showLessFeeds.classList.add('hidden');
      });
    }
    const feedGrid = this.querySelector('.feed-grid:not(.news-feed .feed-grid, .events-feed .feed-grid)');
    if (feedGrid) {
      if (feedGrid.getBoundingClientRect().width <= 500) {
        feedGrid.querySelectorAll('.grid-item').forEach(el => {
          el.style.flexBasis = '100%';
        });
      } else if (feedGrid.getBoundingClientRect().width <= 800) {
        feedGrid.querySelectorAll('.grid-item').forEach(el => {
          el.style.flexBasis = '50%';
        });
      }
    }

    if (typeof this.onFeedLoaded === 'function') {
      this.onFeedLoaded();
    }
    finna.getPromise('lazyImages').then(() => {
      VuFind.observerManager.observe(
        'LazyImages',
        this.querySelectorAll('img[data-src]')
      );
    });

    finna.layout.initToolTips($(this));
  }

  /**
   * Starts process to load the proper feed.
   */
  loadFeed() {
    const cacheItem = this.cache.find(c => { return c.id === this.feedId; });
    if (cacheItem) {
      this.buildFeedDom(cacheItem.responseJSON);
    } else {
      const holder = this;
      // Prepend spinner
      const spinner = document.createElement('span');
      spinner.innerHTML = VuFind.icon('spinner', 'spinner');
      holder.insertAdjacentElement('afterbegin', spinner);

      const url = VuFind.path + '/AJAX/JSON?' + new URLSearchParams({
        method: 'getFeed',
        id: this.feedId,
        'touch-device': this.isTouchDevice
      });
      fetch(url)
        .then(response => response.json())
        .then(responseJSON => {
          const cacheObject = {
            id: this.feedId,
            responseJSON: responseJSON
          };
          this.cache.push(cacheObject);
          this.buildFeedDom(responseJSON);
        }).catch((responseJSON) => {
          // The catch will catch all the js errors in buildFeedDom, so display a warning
          // if something happens
          console.error(responseJSON);
          const titleElement = holder.parentElement.querySelector('.carousel-header');
          if (titleElement) {
            titleElement.classList.add('hidden');
          }
          holder.innerHTML
            = `<!-- Feed could not be loaded: ${responseJSON.data || ''} -->`;
        });
    }

  }

  /**
   * Observed attribute value changed
   * @param {string} name     Name of the attribute
   */
  attributeChangedCallback(name) {
    if ('feed-id' === name) {
      this.classList.remove('hidden');
      this.addToObserver();
    }
  }

  /**
   * Add this element to an intersection observer.
   */
  addToObserver() {
    VuFind.observerManager.createIntersectionObserver(
      'FeedElements',
      (el) => {
        el.loadFeed();
      },
      [this]
    );
  }
}

customElements.define('finna-feed', FinnaFeedElement);
