function loadAllFacets() {
	var expandedFacets = ['authorStr','topic'];
	var facet_length = <?=$this->facet_limit ?> | 0;
	for(var j=0;j<expandedFacets.length;j++) {
		var url = path + '/AJAX/json<?=html_entity_decode($results->getUrlQuery()->getParams()) ?>&method=getAllFacetValues&facetFields[]=' + encodeURIComponent(expandedFacets[j]);
		$.getJSON(url, function (data) {
			for(var i in data.data) {
				var allFacets = {
					'label': data.data[i].data.label,
					'list' : data.data[i].data.list.slice(facet_length)
				};
				if(allFacets.list.length > 0) {
					var html = '<dl class="narrowList navmenu offscreen" id="allGroupHidden_'+allFacets.label+'">';
					var url_base = '<?=$this->currentPath().$results->getUrlQuery()->getParams() ?>';
					for(var i=0;i<allFacets.list.length;i++) {
						html += '<dd><a href="'+url_base+'&filter[]='+allFacets.label+':'+encodeURI(allFacets.list[i].value)+'">'+allFacets.list[i].displayText+'</a> ('+allFacets.list[i].count+')</dd>';
					}
					html += '<dd><a href="#" onclick="lessFacets(\''+allFacets.label+'\'); return false;"><?=$this->transEsc('less')?> ...</a></dd></dl>';
					$(html).insertAfter('#narrowGroupHidden_'+allFacets.label);
					$('#narrowGroupHidden_'+allFacets.label).append('<dd><a href="#" onclick="allFacets(\''+allFacets.label+'\'); return false;"><?=$this->transEsc('all')?> ...</a></dd>')
				}
			}
		});
	}
}

$(document).ready(function() {
  loadAllFacets();
});