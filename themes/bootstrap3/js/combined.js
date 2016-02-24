function setupCombinedResults(container, url) 
{
  container.load(url, '', function(responseText) {
    if (responseText.length == 0) {
      container.hide();
    } else {
      setupEmbeddedOpenUrlLinks(container);
      checkSaveStatuses(container);
      setupSaveRecordLinks(container);
    }
  });
}
    