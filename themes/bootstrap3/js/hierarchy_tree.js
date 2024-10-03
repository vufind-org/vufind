/*global VuFind */
VuFind.register('hierarchyTree', function HierarchyTree() {
  /* Utility functions */
  function selectRecord(treeEl, id) {
    treeEl.querySelectorAll('.hierarchy-tree__selected').forEach(el => el.classList.remove('hierarchy-tree__selected'));
    const selectedEl = treeEl.querySelector('[data-record-id=' + CSS.escape(id) + ']');
    if (!selectedEl) {
      console.error('Could not find tree node for ' + id);
      return;
    }
    const selectedLiEl = selectedEl.closest('li');
    selectedLiEl.classList.add('hierarchy-tree__selected');
  }

  function showRecord(treeEl, id) {
    selectRecord(treeEl, id);
    if (!treeEl.dataset.previewElement) {
      return false;
    }
    const recordEl = document.querySelector(treeEl.dataset.previewElement);
    if (!recordEl) {
      console.error('Record preview element not found');
      return false;
    }
    const queryParams = new URLSearchParams({id: id, source: treeEl.dataset.source});
    fetch(VuFind.path + '/Hierarchy/GetRecord?' + queryParams.toString())
      .then((response) => response.text())
      .then((content) => VuFind.setElementContents(recordEl, VuFind.updateCspNonce(content)) );
    return true;
  }

  function resetTree(treeEl) {
    treeEl.querySelectorAll('.js-toggle-expanded').forEach(el => {
      el.setAttribute('aria-expanded', el.dataset.defaultExpanded);
    });
    treeEl.querySelectorAll('.hierarchy-tree__search-match').forEach(el => el.classList.remove('hierarchy-tree__search-match'));
  }

  function closeTree(treeEl) {
    treeEl.querySelectorAll('.js-toggle-expanded').forEach(el => el.setAttribute('aria-expanded', 'false'));
  }

  function selectSearchMatch(treeEl, id) {
    const selectedEl = treeEl.querySelector('[data-record-id=' + CSS.escape(id) + ']');
    if (!selectedEl) {
      console.error('Could not find node for ' + id);
      return;
    }
    const selectedLiEl = selectedEl.closest('li');
    if (selectedLiEl) {
      selectedLiEl.classList.add('hierarchy-tree__search-match');
      let parentLiEl = selectedLiEl.parentElement.closest('li');
      while (parentLiEl) {
        const toggleEl = parentLiEl.querySelector('.js-toggle-expanded');
        if (toggleEl) {
          toggleEl.setAttribute('aria-expanded', 'true');
        }
        parentLiEl = parentLiEl.parentElement.closest('li');
      }
    }
  }

  function hideFullHierarchy(treeEl) {
    treeEl.querySelectorAll('li').forEach(el => el.classList.add('hidden'));
    let liEl = treeEl.querySelector('.hierarchy-tree__selected');
    while (liEl) {
      liEl.classList.remove('hidden');
      liEl = liEl.parentElement.closest('li');
    }
  }

  function showFullHierarchy(treeEl) {
    treeEl.querySelectorAll('li').forEach(el => el.classList.remove('hidden'));
  }

  function doTreeSearch(containerEl, searchEl, treeEl) {
    const loadIndicatorEl = searchEl.querySelector('.js-load-indicator');
    const searchTextEl = searchEl.querySelector('.js-search-text');
    const searchTypeEl = searchEl.querySelector('.js-search-type');
    const submitBtn = searchEl.querySelector('.js-submit');
    if (!searchTextEl || !searchTypeEl) {
      console.error('Could not find search fields');
      return;
    }

    // Show full hierarchy for search results
    const showEl = containerEl.querySelector('.js-show-full-tree');
    if (showEl && !showEl.checked) {
      showEl.checked = true;
      showFullHierarchy(treeEl);
    }

    const limitMsgEl = containerEl.querySelector('.js-limit-reached');
    const errorMsgEl = containerEl.querySelector('.js-search-error');
    const noResultsMsgEl = containerEl.querySelector('.js-no-results');
    if (limitMsgEl) {
      limitMsgEl.classList.add('hidden');
    }
    if (errorMsgEl) {
      errorMsgEl.classList.add('hidden');
    }
    if (noResultsMsgEl) {
      noResultsMsgEl.classList.add('hidden');
    }
    if (searchTextEl.value === '') {
      resetTree(treeEl);
      return;
    }

    if (loadIndicatorEl) {
      loadIndicatorEl.classList.remove('hidden');
    }
    if (submitBtn) {
      submitBtn.disabled = true;
    }

    const queryParams = new URLSearchParams('format=true');
    queryParams.set('lookfor', searchTextEl.value);
    queryParams.set('type', searchTypeEl.value);
    queryParams.set('hierarchyID', treeEl.dataset.hierarchyId);
    queryParams.set('hierarchySource', treeEl.dataset.source);
    fetch(VuFind.path + '/Hierarchy/SearchTree?' + queryParams.toString())
      .then((response) => response.json())
      .then((data) => {
        if (data.results.length > 0) {
          resetTree(treeEl);
          closeTree(treeEl);
          for (var i = data.results.length; i--;) {
            selectSearchMatch(treeEl, data.results[i]);
          }

          if (data.limitReached && limitMsgEl) {
            limitMsgEl.classList.remove('hidden');
          }
        } else if (noResultsMsgEl) {
          noResultsMsgEl.classList.remove('hidden');
        }
        if (loadIndicatorEl) {
          loadIndicatorEl.classList.add('hidden');
        }
        if (submitBtn) {
          submitBtn.disabled = false;
        }
      })
      .catch(() => {
        if (loadIndicatorEl) {
          loadIndicatorEl.classList.add('hidden');
        }
        if (submitBtn) {
          submitBtn.disabled = false;
        }
        if (errorMsgEl) {
          errorMsgEl.classList.remove('hidden');
        }
      });
  }

  function scrollToSelected(treeEl) {
    const selectedEl = treeEl.querySelector('.hierarchy-tree__selected');
    if (selectedEl) {
      selectedEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }

  function setupTree(containerEl) {
    const treeEl = containerEl.querySelector('.js-hierarchy-tree');
    if (!treeEl) {
      console.error('Could not find tree element');
      return;
    }

    // Setup link click handler on collection page
    if (!('lightbox' in treeEl.dataset) && 'Collection' === treeEl.dataset.context) {
      treeEl.querySelectorAll('.js-record-link').forEach(el => el.addEventListener('click', (ev) => {
        if (showRecord(treeEl, el.dataset.recordId)) {
          ev.preventDefault();
        }
      }));
    }

    // Set Up Partial Hierarchy View
    if (!('fullHierarchy' in treeEl.dataset)) {
      hideFullHierarchy(treeEl);
      const toggleContainerEl = containerEl.querySelector('.js-toggle-full-tree');
      if (toggleContainerEl) {
        const showEl = toggleContainerEl.querySelector('.js-show-full-tree');
        if (showEl) {
          toggleContainerEl.classList.remove('hidden');
          showEl.addEventListener('change', () => {
            if (showEl.checked) {
              showFullHierarchy(treeEl);
            } else {
              hideFullHierarchy(treeEl);
            }
            scrollToSelected(treeEl);
          });
        }
      }
    }

    // Setup search
    const searchEl = containerEl.querySelector('.js-tree-search');
    if (searchEl) {
      searchEl.classList.remove('hidden');
      const submitBtn = searchEl.querySelector('.js-submit');
      if (submitBtn) {
        submitBtn.addEventListener('click', () => doTreeSearch(containerEl, searchEl, treeEl));
      }
      const fieldEl = searchEl.querySelector('.js-search-text');
      if (fieldEl) {
        fieldEl.addEventListener('keyup', (ev) => {
          if (ev.code === 'Enter' && !submitBtn.disabled) {
            doTreeSearch(containerEl, searchEl, treeEl);
          }
        });
      }
    }

    // Scroll to selected element
    scrollToSelected(treeEl);
  }

  function initTree(containerEl) {
    const treePlaceholderEl = containerEl.querySelector('.js-hierarchy-tree-placeholder');
    if (!treePlaceholderEl) {
      console.error('Could not find tree container element');
      return;
    }

    const loadIndicatorEl = containerEl.querySelector('.js-tree-loading');
    if (loadIndicatorEl) {
      loadIndicatorEl.classList.remove('hidden');
    }

    const queryParams = new URLSearchParams(treePlaceholderEl.dataset);
    fetch(VuFind.path + '/Hierarchy/GetTree?' + queryParams.toString())
      .then((response) => response.json())
      .then((json) => {
        if (loadIndicatorEl) {
          loadIndicatorEl.classList.add('hidden');
        }
        VuFind.setElementContents(treePlaceholderEl, VuFind.updateCspNonce(json.html), {}, 'outerHTML');
        setupTree(containerEl);
      })
      .catch(() => {
        if (loadIndicatorEl) {
          loadIndicatorEl.classList.add('hidden');
        }
        treePlaceholderEl.innerHTML = VuFind.translate('error_occurred');
      });
  }

  return { initTree };
});
