nelioab_nav(jQuery);
jQuery(document).ready(function(){
	if ( typeof( nelioab_prepare_links_for_nav_to_external_pages ) == "function" )
		nelioab_prepare_links_for_nav_to_external_pages(jQuery);
	if ( typeof( nelioab_add_hidden_fields_on_forms ) == "function" )
		nelioab_add_hidden_fields_on_forms(jQuery);
	if ( typeof( nelioabStartHeatmapTracking ) == "function" )
		nelioabStartHeatmapTracking();
});
