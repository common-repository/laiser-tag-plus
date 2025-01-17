
ltpoc.ImageManager = CFBase.extend({
	init: function() {
		var poppet = this;
		jQuery(document).ready(function() {
			jQuery(window).resize(function() {
				setTimeout(function() {
					poppet.pageForFilmstripSize();
				}, 500);
			});
		});
	},
	
	// general behavior we're looking for is that if we hang on to the last used
	// set of tags when we're asked to search for no tags.
	//	
	// all three arguments are optional.
	// the behavior when calling without arguments will depend on the last-set mode.
	//
	// when in current or currentSuggested, it will pull whatever are the current/suggested
	// tags at that time, plus the current page number.
	//
	// when in specificTags mode, it will use the last-used set of tags.
	// 
	// obviously, you can explicitly reset pagination by setting resetPagination to true,
	// and explicitly send a list of tags to search on in the last argument (as a string,
	// not an array).
	// 
	pingFlickr: function(mode, resetPagination, tagList) {
		var poppet = this;
		var tags = '';
		var tagsArray = [];
		
		if (!mode) {
			mode = this.mode;
		}
		
		switch (mode) {
			case this.mode_current:
				tags = ltpoc.tagManager.tagsAsCSV({which:'current', filter:'imageSearch'});
			break;
			case this.mode_currentSuggested:
				tags = ltpoc.tagManager.tagsAsCSV({which:'current,suggested', filter:'imageSearch'});
			break;
			case this.mode_specificTags:
				if (tagList) {
					tags = tagList;
				}
				else if (this.lastSearchTagList.length) {
					tags = this.lastSearchTagList;
				}
				else {
					this.mode = this.mode_none;
				}
			break;
			default:
			case this.mode_none:
			break;
		}

		if (tags.length === 0) {
			return;
		}
		else {
			this.mode = mode;
		}

		if (this.mode !== this.mode_none) {
			tagsArray = tags.split(',');
			this.lastSearchTagList = this.prettifyTagList(tags);
		}

		if (resetPagination) {
			this.currentPage = 1;
		}
		
		if (tagsArray.length > 1 || (tagsArray.length === 1 && tagsArray[0].length > 0)) {
			this.pageForFilmstripSize();
			jQuery('#ltpoc_images_result_tags').html('\
				<div id="ltpoc_image_searching">\
				Searching for page ' + this.currentPage + ' of ' + this.lastSearchTagList + '\
				</div>\
			');
			jQuery.ajax({
				type: 'POST',
				url: 'index.php',
				dataType: 'html',
				data: { 
					ltpoc_action: 'api_proxy_flickr',
					tags: tagsArray.slice(0, 5).join(','),	// todo: figure out a good way to limit this
					per_page: this.imagesPerPage,
					page: this.currentPage,
					sort: this.sortMode + '-' + this.sortDirection
				},
				success: function(responseString) {
					try {
						poppet.handleFlickrSearchResponse(responseString);
					}
					catch (error) {
						if (error.name === 'ltpoc_connection_failure') {
							error.string = 'Could not contact Flickr.';
							ltpoc.handleCalaisError(error);
						}
						else {
							throw error;
						}
					}
					finally {
						ltpoc.hideWorkingIndicator(responseString);
					}

				},
				error: function(responseString) {
					ltpoc.hideWorkingIndicator(responseString);
					ltpoc.handleAjaxFailure();
				}
			 });
		}
	},

	handleFlickrSearchResponse: function(responseJSON) {
		if (responseJSON.indexOf('__ltpoc_request_failed__') >= 0) {
			throw { name: 'ltpoc_connection_failure' };
		}
		
		var poppet = this;
		// release previous image set. if there's a previewed image, we still have a reference to it in
		// previewedImage.
		this.images = [];
		eval('poppet.latestResponse = ' + (responseJSON || 'null') + ';');
		if (this.latestResponse.stat === 'ok') {

			this.nPages = this.latestResponse.photos.pages;
			this.currentPage = this.latestResponse.photos.page;
			
			for(var i = 0; i < this.latestResponse.photos.photo.length; i++) {
				this.images.push(new ltpoc.FlickrImage(this.latestResponse.photos.photo[i]));
			}
			
			this.filmstripBox.removeTokens();
			for (var t = 0; t < this.images.length; t++) {
				this.filmstripBox.addToken(new ltpoc.ImageToken(this.images[t]));
			}
			
			// if we didn't get any response ...
			if (this.latestResponse.photos.pages === 0) {
				this.currentPage = 0;
				if (this.firstPing) {
					this.filmstripBox.showEmptyMessage();
				}
			}
			
			this.updatePagingDisplay();
			jQuery('#ltpoc_images_result_tags').html('\
				Searched for: ' + this.lastSearchTagList + 
				'<div>\
					Search For: <a href="javascript:ltpoc.imageManager.pingFlickr(ltpoc.imageManager.mode_current, true);">Current Tags</a>,\
					<a href="javascript:ltpoc.imageManager.pingFlickr(ltpoc.imageManager.mode_currentSuggested, true);">Current and Suggested Tags</a>\
				</div>\
			');
		}
		this.firstPing = false;
	},
	
	updatePagingDisplay: function() {
		if (this.currentPage - 1 < 1) {
			jQuery('#ltpoc_images_page_back').addClass('disabled');
		}
		else {
			jQuery('#ltpoc_images_page_back').removeClass('disabled');
		}
		if (this.currentPage + 1 > this.nPages) {
			jQuery('#ltpoc_images_page_fwd').addClass('disabled');
		}
		else {
			jQuery('#ltpoc_images_page_fwd').removeClass('disabled');
		}
		if (this.currentPage > 0) {
			jQuery('#ltpoc_images_page_num').html('Page ' + this.currentPage + ' of ' + this.nPages).show();
		}
		else {
			jQuery('#ltpoc_images_page_num').html('&nbsp').hide();
		}

	},
	
	prettifyTagList: function(tagList) {
		if (tagList[0] === ',') {
			tagList = tagList.substring(1);
		}
		return tagList.split(',').join(', ');
	},
	pageForward: function() {
		if ((this.currentPage + 1) <= this.nPages) {
			this.currentPage++;
			this.pingFlickr();
		}
	},
	pageBack: function() {
		if ((this.currentPage - 1) >= 1) {
			this.currentPage--;
			this.pingFlickr();
		}
	},
	
	// mode = [date-posted | date-taken | interesting-ness]
	setSortMode: function(mode) {
		this.sortMode = mode;
		this.currentPage = 1;
		this.pingFlickr();		
	},
	// dir = [asc | desc]
	setSortDirection: function(dir) {
		this.sortDirection = dir;
		this.currentPage = 1;
		this.pingFlickr();
	},
	
	closePreview: function() {
		jQuery('#ltpoc_image_preview').slideUp('fast');
		this.previewOpen = false;
		this.highlightPreviewedImageToken(false);		
		this.previewedImage = null;
	},
	
	previewImage: function(image) {
		var clear = (jQuery.browser.msie ? '' : ' clear');
		var insertList = '\
			Choose size:\
			<ul id="ltpoc_preview_insert_sizes">\
				<li class="square" onclick="ltpoc.imageManager.insertPreviewedImageInPost(\'s\'); return false;" title="Square: 75px">[Square 75px]</li>\
				<li class="thumb" onclick="ltpoc.imageManager.insertPreviewedImageInPost(\'t\'); return false;" title="Thumbnail: 100px">[Thumb 100px]</li>\
				<li class="small ' + clear + '" onclick="ltpoc.imageManager.insertPreviewedImageInPost(\'m\'); return false;" title="Small: 200px">[Small 200px]</li>\
				<li class="medium" style="clear:top" onclick="ltpoc.imageManager.insertPreviewedImageInPost(\'\'); return false;" title="Medium: 500px">[Medium 500px]</li>\
			</ul>\
			<div class="clear"></div>\
		';
		
		var tags = image.getTagsString();
		if (tags.length) {
			var tagsArray = tags.split(' ');
			var nTags = tagsArray.length;
			var diff = '';
			if (nTags > 15) {
				diff = ' and ' + (nTags - 15) + ' more ...';
				tagsArray = tagsArray.slice(0, 15);
			}
			tags = '<dt>Tags</dt><dd>' + tagsArray.join(', ') + diff + '</dd>';
		}
		var jqPreview = jQuery('#ltpoc_image_preview');
		var currentHeight = jqPreview.height();
		var currentWidth = jqPreview.width();
		var previewHTML = '\
			<div id="ltpoc_preview_sizer" style="float:right; height:' + currentHeight + 'px; width:1px"></div>\
			<div class="ltpoc_preview_info" style="visibility:hidden; display:none;">\
				<h4><a href="' + image.getImagePageURL() + '">' + image.getTitle() + '</a></h4>\
				' + insertList + '\
				<dl>\
					<dt>Source</dt>\
					<dd><a href="' + image.getSourceURL() + '">' + image.getSourceName() + '</a></dd>\
					<dt>Author</dt>\
					<dd><a href="' + image.getAuthorURL() + '">' + image.getAuthor() + '</a></dd>\
					<dt>License</dt>\
					<dd><a href="' + image.getLicensePageURL() + '"><img src="' + image.getLicenseIconURL() + '" /></a></dd>\
					' + tags + '\
				</dl>\
			</div>\
			<div class="frame"><img class="ltpoc_preview_img" src="' + image.getImageURL() + '" title="' + image.getTitle() +'" style="display:none;"/></div>\
			<div class="clear"></div>\
			<span id="ltpoc_close_preview_button" onclick="ltpoc.imageManager.closePreview();"><span>[x]</span></span>\
		';
		jqPreview.html(previewHTML);
		if (!this.previewOpen) {
			this.previewOpen = true;
			jqPreview.slideDown('fast');
		}
		if (this.previewedImage) {
			this.highlightPreviewedImageToken(false);
		}
		var poppet = this;
		jQuery('#ltpoc_image_preview img.ltpoc_preview_img').load(function() { poppet.previewImageLoaded(image); });
		this.previewImageLoadingTimer = setTimeout(
			function() {
				jQuery('#ltpoc_close_preview_button').addClass('loading');
			}, 
			200
		);
		
	},
	
	highlightPreviewedImageToken: function(onOrOff) {
		var poppet = this;
		jQuery.each(this.filmstripBox.tokens, function(i, token) {
			if (token.image && token.image === poppet.previewedImage) {
				token.highlightInline(onOrOff);
			}
		});
	},
	
	insertPreviewedImageInPost: function(size) {
		if (this.previewedImage) {
			this.insertImageInPost(this.previewedImage, size);
		}
	},
	
	insertImageInPost: function(image, size) {
		var html = '<a href="' + image.getImagePageURL() + '" title="' + image.getTitle() + '"><img src="' + image.getImageURL(size) + '" /></a>';
		var rte = ltpoc.getRTE();
		if (rte && !rte.isHidden()) {
			if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
				tinyMCE.get('content').editorCommands.execCommand('mceInsertContent', false, html);
			}			
		}
		else if (edCanvas) {
			edInsertContent(edCanvas, html);
		}
	},
	thumbWidth: 86,
	pageForFilmstripSize: function() {
		var filmstripFrame = cf.pageFrame(jQuery('#ltpoc_filmstrip_wrapper').get(0));
		var n = Math.floor((filmstripFrame.width - 138) / this.thumbWidth);	// make room for the pagination controls and scroller
		if (this.imagesPerPage !== n) {
			this.imagesPerPage = n;
			this.pingFlickr();
		}
		jQuery('#cf_tokenbox_image_filmstrip').width(n * this.thumbWidth);
	},
	
	previewImageLoaded: function(image) {
		jQuery('#ltpoc_preview_sizer').remove();
		clearTimeout(this.previewImageLoadingTimer);
		jQuery('#ltpoc_close_preview_button').removeClass('loading');
		setTimeout(function(){ ltpoc.imageManager.resizePreviewInfoPane(); }, 0);
		jQuery('#ltpoc_image_preview div.ltpoc_preview_info').css('visibility', 'visible').fadeIn();
		jQuery('#ltpoc_image_preview img.ltpoc_preview_img').fadeIn();
		this.previewedImage = image;
		this.highlightPreviewedImageToken(true);		
	},
	
	resizePreviewInfoPane: function() {
		this.lastPreviewInfoWidth = jQuery('#ltpoc_image_preview').width() - jQuery('#ltpoc_image_preview img.ltpoc_preview_img').width() - 52;
		jQuery('#ltpoc_image_preview div.ltpoc_preview_info').width(this.lastPreviewInfoWidth);
	},
	
	latestResponse: {},
	images: [],
	
	mode_none: 0,					// show nothing	
	mode_currentSuggested: 1,		// search for current and suggested
	mode_current: 2,				// search for current
	mode_specificTags: 3,			// search for these specific tags
	
	mode: 0,
	firstPing: true,
	
	searchTags: '',
	lastSearchTagList: '',
	
	previewOpen: false,
	previewedImage: null,
	lastPreviewInfoWidth: 175,
	previewImageLoadingTimer: 0,
	
	filmstripBox: null,
	currentPage: 1,
	nPages: 0,
	imagesPerPage: 7,
	sortMode: 'interestingness',
	sortDirection: 'desc',
	flickrLicenseInfo: {},		// NB this is set by our php file
	ccLicenseIconMap: {
		'0': '',
		'4': 'http://i.creativecommons.org/l/by/3.0/80x15.png',
		'6': 'http://i.creativecommons.org/l/by-nd/3.0/80x15.png',
		'3': 'http://i.creativecommons.org/l/by-nc-nd/3.0/80x15.png',
		'2': 'http://i.creativecommons.org/l/by-nc/3.0/80x15.png',
		'1': 'http://i.creativecommons.org/l/by-nc-sa/3.0/80x15.png',
		'5': 'http://i.creativecommons.org/l/by-sa/3.0/80x15.png'
	}
	
});

ltpoc.imageManager = new ltpoc.ImageManager;
