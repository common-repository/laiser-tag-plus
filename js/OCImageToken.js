
ltpoc.ImageToken = CFToken.extend({
	init: function(image) {
		this._super(image.getTitle(), 'ltpoc_imageToken_' + cf.slugify(image.getTitle()) + new Date().valueOf());
		this.image = image;
	},
	getInlineClass: function() {
		return 'ltpoc_imageToken inline';
	},	
	getOverlayClass: function() {
		return 'ltpoc_imageToken overlay';
	},
	
	shouldShowOverlay: function() {
		return !ltpoc.imageManager.previewOpen;
	},
	
	getOverlayFrame: function(inlineFrame, overlayFrame) {
		var h = overlayFrame.height;
		var inlineCenter = inlineFrame.left + (inlineFrame.width / 2.0);
		var left = inlineFrame.left;
		
		return {
			left: left,
			top: inlineFrame.top + inlineFrame.height,
			width: 250,
			height: h
		};
	},	
	getContentHTML: function(mode) {
		if (mode === 'inline') {
			return '<img src="' + this.image.getImageURL('s') +'" />';
		}
		else if (mode === 'overlay') {
			var r = '\
				<div class="cf_balloon imageToken">\
					<h4><a href="' + this.image.getImagePageURL() + '">' + this.image.getTitle() + '</a></h4>\
					<dl>\
						<dt>Source</dt>\
						<dd><a href="' + this.image.getSourceURL() + '">' + this.image.getSourceName() + '</a></dd>\
						<dt>Author</dt>\
						<dd><a href="' + this.image.getAuthorURL() + '">' + this.image.getAuthor() + '</a></dd>\
						<dt>License</dt>\
						<dd><a href="' + this.image.getLicensePageURL() + '">' + this.image.getLicense() + '</a></dd>\
					</dl>\
					<div class="clear"></div>\
				</div>';
			return r;
		}
	},
	inlineClicked: function(e) {
		if (ltpoc.imageManager.previewedImage === this.image) {
			ltpoc.imageManager.closePreview();
		}
		else {
			ltpoc.imageManager.previewImage(this.image);
		}
	},
	
	inlineDoubleClicked: function(e) {
		ltpoc.imageManager.insertImageInPost(this.image, 'm');
	},
	
	wasInsertedIntoDOM: function() {
		this._super();
	},
	overlayShouldBeDraggable: function() {
		return false;
	},
	highlightInline: function(onOrOff) {
		if (onOrOff) {
			this.jqInline.addClass('highlight');
		}
		else {
			this.jqInline.removeClass('highlight');
		}

	},
	image: null	// NB this is a ref to the full-fledged image object, not just a string
		
});