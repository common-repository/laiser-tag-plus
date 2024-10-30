
ltpoc.rte = null;
ltpoc.scanOnIdle = ltpoc.autoFetch;
ltpoc.cachedPostContent = '<br>';
ltpoc.lastResponse = null;
ltpoc.docLangWorkaround = true;
ltpoc.tagTypes = {};

if (typeof(console) === 'undefined') {
	console = {log:function() {}, dir: function(){}};
}

/**
 * Note: this should be stored as a string, not a number.
 */
ltpoc.getFormatVersion = function() {
	return '1.1';
};

ltpoc.prepTagType = function(artifact) {
	var type = artifact.type.name;
	var tagdata = {};
	if ( 'Company' === type ) {
		// Dont store companies w/o permids
		if ( undefined !== artifact.permID ) {
			tagdata.name = artifact.name;
			tagdata.fullName = artifact.fullName;
			tagdata.permID = artifact.permID;
			tagdata.ticker = artifact.ticker;
		}
	}
	else {
		tagdata.name = artifact.name;
	}

	if ( undefined === ltpoc.tagTypes[type] ) {
		ltpoc.tagTypes[type] = [];
	}

	if ( undefined !== tagdata.name ) {
		ltpoc.tagTypes[type].push(tagdata);
	}
}

ltpoc.showTagSearchingIndicator = function() {
	if (ltpoc.postHasSelection()) {
		jQuery('#ltpoc_tag_searching_indicator').html('Finding tags for selection&hellip;');
	}
	jQuery('#ltpoc_api_notifications').html('').hide();
	jQuery('#ltpoc_suggest_tags_link').hide();
	jQuery('#ltpoc_tag_searching_indicator').show();
};
ltpoc.hideTagSearchingIndicator = function() {
	jQuery('#ltpoc_suggest_tags_link').show();
	jQuery('#ltpoc_tag_searching_indicator').hide().html('Finding tags&hellip;');
};

ltpoc.pingCalais = function() {
	var selection = ltpoc.getSelectedPostText();
	var text = selection.length ? selection : ltpoc.getPostText();

	if (ltpoc.docLangWorkaround && text.length <= 64 ) {
		ltpoc.tagManager.deleteUnusedSuggestedTags();
		jQuery('#ltpoc_api_notifications').html('Laiser Tag Plus needs at least 64 characters to start searching for tags.').show();
		return;
	}

	ltpoc.showTagSearchingIndicator();
	jQuery.ajax({
		type: 'POST',
		url: 'index.php',
		dataType: 'text',
		data: {
			ltpoc_action: 'api_proxy_oc',
			text: text
		},
		success: function(responseString) {
			try {
				ltpoc.handleCalaisResponse(responseString);
			}
			catch (error) {
				if (error.type && error.type === '__ltpoc_request_failed__') {
					ltpoc.handleCalaisError(error);
				}
				else {
					throw error;
				}
			}
			finally {
				ltpoc.hideTagSearchingIndicator();
				ltpoc.handleFlickrError();
			}
		},
		error: function() {
			ltpoc.hideTagSearchingIndicator();
			ltpoc.handleAjaxFailure();
		}
	 });
};

ltpoc.handleCalaisError = function(error) {
	jQuery('#ltpoc_api_notifications').html('<span style="color:red;"><strong>Error</strong>: ' + error.string).show();
};

// we couldn't even reach our own server
ltpoc.handleAjaxFailure = function(requestObj, errorString, exception) {
	jQuery('#ltpoc_api_notifications').html('<span style="color:red;"><strong>Error talking to WordPress server</strong>: ' + errorString);
};

ltpoc.handleFlickrError = function() {
	jQuery('#ltpoc_api_notifications').html('<span style="color:red;"><strong>Error finding images.</strong>');
};

ltpoc.hideWorkingIndicator = function(responseString) {
	if (responseString.substr(0, 14) === '{"stat":"fail"') {
		jQuery('#ltpoc_api_notifications').html('<span style="color:red;"><strong>Error searching for images. Try using less tags.</strong></span>');
	}
	jQuery('#ltpoc_image_searching').hide();
};


ltpoc.handleCalaisResponse = function(responseString) {
	if (responseString.indexOf('__ltpoc_request_failed__') >= 0) {
		eval('var errorObject = ' + responseString.substring('__ltpoc_request_failed__'.length));
		if (!ltpoc.docLangWorkaround || (errorObject.error.indexOf('Unsupported document language') === -1)) {
			throw { type: '__ltpoc_request_failed__', string: errorObject.error };
		}
		else {
			ltpoc.tagManager.deleteUnusedSuggestedTags();
			jQuery('#ltpoc_api_notifications').html('<span>No new tags extracted.<br/><a href="javascript:ltpoc.pingCalais();">Suggest Tags</a></span>');
		}
	}
	try {
		ltpoc.lastResponse = jQuery.xmlToJSON(jQuery.parseXML(responseString));
	}
	catch (error) {
		throw error;
	}


	if (ltpoc.isValidResponse(ltpoc.lastResponse) && ltpoc.lastResponse.Description.length > 0) {

		jQuery('#ltpoc_suggest_tags_link').show();

		ltpoc.tagManager.deleteUnusedSuggestedTags();

		var artifacts = ltpoc.artifactManager.generateArtifacts(ltpoc.lastResponse.Description);
		var newTags = [];

		jQuery.each(artifacts, function(i, artifact) {
			if (artifact.shouldGenerateTag()) {
				// Pret tag data for storage
				ltpoc.prepTagType(artifact);
				var resolvedArtifact = artifact;
				if (artifact.isAmbiguous()) {
					resolvedArtifact = ltpoc.artifactManager.resolveAmbiguousEntity(artifact);
					if (resolvedArtifact) {
						resolvedArtifact.willResolveAmbiguousArtifact(artifact);
					}
					else {
						resolvedArtifact = artifact;
					}
				}
				if (resolvedArtifact) {
					var newTag = ltpoc.tagManager.createTagIfNew(resolvedArtifact.getTagText(), resolvedArtifact);
					if (newTag) {
					    if(newTag.text.indexOf('Draft:') > -1) {
					        newTag.text = newTag.text.replace('Draft:', '');
					        newTag.wpSlug = newTag.wpSlug.replace('draft', '');
                        }
						newTags.push(newTag);
					}
				}
			}
		});
		jQuery('#ltpoc_tag_data').val(JSON.stringify(ltpoc.tagTypes));

		ltpoc.tagManager.normalizeRelevance();

		jQuery.each(newTags, function(i, tag) {
			// if the tag type is set and its 0 or the type is an EventFact and its 0, don't display the tag
			if ( !(
				( ltpoc.allowedTagTypes.hasOwnProperty(tag.type) && 0 === ltpoc.allowedTagTypes[tag.type] )
				||
				( 'EventFact' === tag.source._className && 0 === ltpoc.allowedTagTypes.EventFact )
			)) {
				if (ltpoc.relevanceIsSufficient(tag.source.getNormalizedRelevance())) {
					ltpoc.tagManager.putTagInSuggested(tag, 'auto');
				}
				else {
					ltpoc.tagManager.putTagInBlacklist(tag, 'auto');
				}
			}
		});

		if (ltpoc.tagManager.suggestedTags.length === 0) {
			jQuery('#ltpoc_api_notifications').html('<span>No new tags extracted.<br/><a href="javascript:ltpoc.pingCalais();">Suggest Tags</a></span>');
		}
		else {
			if (ltpoc.imageManager.mode === ltpoc.imageManager.mode_none) {
				ltpoc.imageManager.pingFlickr(ltpoc.imageManager.mode_currentSuggested);
			}
			else if (ltpoc.imageManager.mode !== ltpoc.imageManager.mode_specificTags) {
				ltpoc.imageManager.pingFlickr();
			}
		}
	}

};

ltpoc.isValidResponse = function(responseObject) {
	return ((typeof(ltpoc.lastResponse) !== 'undefined') && typeof ltpoc.lastResponse.RootName !== 'undefined' && ltpoc.lastResponse.RootName === 'rdf:RDF');
};

ltpoc.relevanceIsSufficient = function(relevance) {
	switch (ltpoc.minimumRelevance) {
		case 'high':
			return relevance > .66;
		case 'medium':
			return relevance > .33;
		case 'any':
		default:
			return true;
	}
};

ltpoc.tickleIdleTimer = function() {
	if(ltpoc.idleTimer) {
		clearTimeout(ltpoc.idleTimer);
	}
	ltpoc.idleTimer = setTimeout(ltpoc.idleTimeout, 1000);
};

ltpoc.firstScan = true;
ltpoc.idleTimeout = function() {
	if (ltpoc.scanOnIdle && ltpoc.postIsDirty(true)) {
		ltpoc.pingCalais();
	}
	if (ltpoc.firstScan) {
		// if we have any current tags, go ahead and fire off an image request
		if (ltpoc.tagManager.currentTags.length) {
			ltpoc.imageManager.pingFlickr(ltpoc.imageManager.mode_currentSuggested);
		}
		ltpoc.firstScan = false;
	}
};

ltpoc.postIsDirty = function(updateCache) {
	var content = ltpoc.getPostText();
	var changed = (content !== ltpoc.cachedPostContent) || ltpoc.firstScan;
	if (typeof(updateCache) !== 'undefined') {
		ltpoc.cachedPostContent = content;
	}
	return changed;
};

ltpoc.getRTE = function() {
	// we used to be notified when the rte was hidden. as of 2.8.4 (at least), we're not.
	if (jQuery('textarea#content:visible').size()) {
		return null;
	}
	return ltpoc.rte;
};

ltpoc.postHasSelection = function() {
	return (ltpoc.getSelectedPostText().length > 0);
};

ltpoc.getSelectedPostText = function() {
	var rte = ltpoc.getRTE();
	if (rte !== null) {
		return selectedHTML = rte.selection.getContent({format : 'html'});
	}
	else if (jQuery('#content').size() > 0) {
		var jqTextarea = jQuery('#content');
		var selectedText = '';
		if ('getSelection' in window) {
			// moz, webkit
			selectedText = jqTextarea.val().substring(jqTextarea[0].selectionStart, jqTextarea[0].selectionEnd);
		}
		else {
			// IE
			if (document.selection.type === 'Text') {
				var range = document.selection.createRange();
				selectedText = range.htmlText;
			}
		}
		return selectedText;
	}
	return '';
};

ltpoc.getPostText = function() {
	var rte = ltpoc.getRTE();
	if (rte !== null) {
		return rte.getContent({format : 'html'});
	}
	else if (jQuery('#content').size() > 0) {
		return jQuery('#content')[0].value;
	}
	return '';
};

ltpoc.windowResized = function() {
	if (ltpoc.wp_gte_23 && !ltpoc.wp_gte_25) {
		ltpoc.imageManager.pageForFilmstripSize();
		ltpoc.imageManager.resizePreviewInfoPane();
	}
};

ltpoc.addTagFieldHandler = function() {
	var tagsString = jQuery('#ltpoc_add_tag_field').val();
	if (tagsString) {
		var tagTexts = tagsString.split(/\s*,\s*/);
		jQuery.each(tagTexts, function(i, tagText) {
			if (tagText.length > 0) {
				var newTag = ltpoc.tagManager.createTagIfNew(tagText);
				if (newTag) {
					ltpoc.tagManager.putTagInCurrent(newTag);
					jQuery('#ltpoc_add_tag_field').val('');
				}
				else {
					jQuery('#ltpoc_current_tag_notifications').html('Tag already exists.');
				}
			}
		});
	}
	return false;
};

// So we need to give the autocomplete a chance to do the completion for us if it wants to.
// We look for enter key events at both the tag entry field and at the ltpoc_tag_controls
// container. At the field, we set a flag saying "let's wait to see if autocomplete fires."
// At the container, we check to see if it's fired. If it hasn't we can go ahead and add the
// tag. If it did fire, it will invoke this method, tagAutocompleteHandler.
ltpoc.waitingForTagAutocomplete = false;
ltpoc.tagAutocompleteHandler = function() {
	jQuery('#ltpoc_add_tag_button').click();
	ltpoc.waitingForTagAutocomplete = false;
};

ltpoc.updateArchiveField = function() {
	var v = '{\
		"version":"' + ltpoc.getFormatVersion() + '",\
		"tags": ' + ltpoc.tagManager.getSerializedTags() + '\
	}';
	jQuery('#tags-input').val(ltpoc.tagManager.tagsAsCSV('current'));
	jQuery('#ltpoc_metadata').val(v);
};

// temporary solution
ltpoc.unarchiveSavedTags = function(wpTags) {
	var j = jQuery('#ltpoc_metadata');
	if (j.size() && j.val() !== '') {
		var archive = eval('(' + j.val() + ')');
		switch (archive.version.toString()) {
			case '1':
			case '1.0':
				jQuery.each(archive.tags, function(slug, tag) {
					if (tag.source !== null) {
						ltpoc.artifactManager.unarchiveArtifact(tag.source, archive.version);
					}
				});
				var artifacts = ltpoc.artifactManager.getArtifacts();
				jQuery.each(artifacts, function(i, artifact) {
					if (artifact.shouldGenerateTag && artifact.shouldGenerateTag()) {
						var newTag = ltpoc.tagManager.createTagIfNew(artifact.getTagText(), artifact);

						if (newTag) {
							jQuery.each(archive.tags, function(slug, tag) {
								if (newTag.text === tag.text) {
									switch(tag.bucketName) {
										case 'current':
											// be sure it still exists for WP
											if (wpTags.indexOf(newTag.text) !== -1) {
												ltpoc.tagManager.putTagInCurrent(newTag, newTag.getBucketPlacement());
											}
											else {
												ltpoc.tagManager.deleteTag(newTag);
											}
										break;
										case 'suggested':
											ltpoc.tagManager.putTagInSuggested(newTag, newTag.getBucketPlacement());
										break;
										case 'blacklisted':
											ltpoc.tagManager.putTagInBlacklist(newTag, newTag.getBucketPlacement());
										break;
									}
								}
							});
						}
					}
				});
			break;
			case '1.1':
				var tag = null;
				jQuery.each(archive.tags, function(slug, dehydratedTag) {
					tag = CFBase.unserialize(dehydratedTag, 'ltpoc.');
					if (tag) {
						switch (tag.getBucketName()) {
							case 'current':
								if (wpTags.indexOf(dehydratedTag.text) !== -1) {	// if it still exists for WP
									ltpoc.tagManager.putTagInCurrent(tag, tag.getBucketPlacement());
								}
								else {
									ltpoc.tagManager.deleteTag(tag);
								}
							break;
							case 'suggested':
								ltpoc.tagManager.putTagInSuggested(tag, tag.getBucketPlacement());
							break;
							case 'blacklisted':
								ltpoc.tagManager.putTagInBlacklist(tag, tag.getBucketPlacement());
							break;
						}
					}
				});
			break;

		}
	}
};

ltpoc.initPostEditPage = function() {
	// remove wp's tag control
	var wpTagList = (ltpoc.wp_gte_28 ? jQuery('#tagsdiv-post_tag .the-tags').val() : jQuery('#tags-input').val());

	// if for whatever reason we don't find a valid tag input then bail
	if (wpTagList === undefined) {
		return false;
	}

	if (ltpoc.wp_gte_23 && !ltpoc.wp_gte_25) {
		jQuery('#tagdiv').remove();
		jQuery('#grabit').prepend(jQuery('#ltpoc_dbx'));
		var ocDBXGroup = new dbxGroup(
			'oc-dbx',
			'vertical',
			'7',
			'yes',
			'10',
			'yes',
			'open',
			'open',
			'close',
			'Click and Drag to move',
			'Click to %toggle%',
			'Use the arrow keys to move',
			', or press the enter key to %toggle% it',
			'%mytitle%  [%dbxtitle%]'
		);
		jQuery('#ltpoc_tag_controls').append('<input id="tags-input" type="hidden" value="" name="tags_input"/>');
	}
	else if (ltpoc.wp_gte_28) {
		jQuery('#tagsdiv-post_tag').remove();
		jQuery('#ltpoc_tag_controls div.inside').append('<input id="tags-input" type="hidden" value="" name="tax_input[post_tag]"/>');
	}
	else if (ltpoc.wp_gte_25) {
		jQuery('#tagsdiv').remove();
		jQuery('#ltpoc_tag_controls div.inside').append('<input id="tags-input" type="hidden" value="" name="tags_input"/>');
	}

	// set up buckets
	ltpoc.tagManager.suggestedBox = new ltpoc.SuggestedTagBox();
	ltpoc.tagManager.currentBox = new ltpoc.CurrentTagBox();
	ltpoc.tagManager.blacklistedBox = new ltpoc.BlacklistedTagBox();

	ltpoc.tagManager.suggestedBox.insertIntoDOM('append', jQuery('#ltpoc_suggested_tags_wrapper'));
	ltpoc.tagManager.currentBox.insertIntoDOM('append', jQuery('#ltpoc_current_tags_wrapper'));
	ltpoc.tagManager.blacklistedBox.insertIntoDOM('append', jQuery('#ltpoc_suggested_tags_wrapper'));

	var wpTags = wpTagList.split(/\s*,\s*/);
	ltpoc.unarchiveSavedTags(wpTags);

	// create new tags for any wp tags we don't know about.
	if (wpTagList.length > 0) {
		jQuery.each(wpTags, function(i, tagName) {
			if (tagName.length > 0) {
				ltpoc.tagManager.putTagInCurrent(ltpoc.tagManager.createTagIfNew(tagName));
			}
		});
	}

	jQuery('#ltpoc_tag_controls').keypress(function(e) {
		// if autocomplete did not fire we go ahead and add the tag
		// and kill the event.
		if (e.which === 13 && ltpoc.waitingForTagAutocomplete) {
			jQuery('#ltpoc_add_tag_button').click();
			ltpoc.waitingForTagAutocomplete = false;
			return false;
		}
	});

	jQuery('#ltpoc_add_tag_button').click(ltpoc.addTagFieldHandler);
	jQuery('#ltpoc_add_tag_field').keypress(function(e) {
		if (e.which === 13) {
			// let's see if autocomplete will fire
			ltpoc.waitingForTagAutocomplete = true;
		}
		else {
			jQuery('#ltpoc_current_tag_notifications').html('&nbsp;');
		}

	});

	var url = 'admin-ajax.php?action=ajax-tag-search';
	var options = {
		delay: 500,
		minchars: 2,
		onSelect: ltpoc.tagAutocompleteHandler
	};

	if (ltpoc.wp_gte_28) {
		url += '&tax=post_tag';
		options.multiple = true;
		options.multipleSep = ', ';
	}

	jQuery('#ltpoc_add_tag_field').suggest(url, options);

	// images
	ltpoc.imageManager.filmstripBox = new ltpoc.ImageParadeBox();
	ltpoc.imageManager.filmstripBox.insertIntoDOM('append', jQuery('#ltpoc_filmstrip_wrapper'));
	jQuery('#ltpoc_images_page_fwd').click(function() {
		ltpoc.imageManager.pageForward();
	});
	jQuery('#ltpoc_images_page_back').click(function() {
		ltpoc.imageManager.pageBack();
	});
	jQuery('#ltpoc_images_sort_select').change(function() {
		ltpoc.imageManager.setSortMode(jQuery('option:selected', jQuery(this)).val());
	});
	jQuery('input[name=ltpoc_sort_direction]').change(function() {
		ltpoc.imageManager.setSortDirection(jQuery('input:checked[name=ltpoc_sort_direction]').val());
	});
	jQuery('#ltpoc_images_sort_toggle').click(function() {
		jQuery('#ltpoc_images_sort').slideToggle();
		return false;
	});
	jQuery('#ltpoc_suggest_tags_link').click(function() { ltpoc.pingCalais(); return false; });
	if (!ltpoc.scanOnIdle) {
		jQuery('#ltpoc_suggest_tags_link').show();
	}
	jQuery('#content').keyup(function(e) {
		ltpoc.tickleIdleTimer();
	});
	jQuery(window).resize(function() {
		ltpoc.windowResized();
	});
	ltpoc.tickleIdleTimer();
};

jQuery(document).ready(ltpoc.initPostEditPage);
