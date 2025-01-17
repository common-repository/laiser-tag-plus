
ltpoc.TagBox = CFTokenBox.extend({
	init: function(id_tag, title) {
		this._super(id_tag, title);
	},
	
	getListClass: function() {
		return 'cf_tokenbox cloud';
	},	
	updateTitleCount: function(nTokens) {
		var count = nTokens > 0 ? ' (' + nTokens + ')' : '';
		jQuery('#cf_tokenbox_header_' + this.id_tag).html(this.title + count);
	},
	removeToken: function(token) {
		this._super(token);
		this.updateTitleCount(this.tokens.length);
	},
	addToken: function(token) {
		this._super(token);
		this.updateTitleCount(this.tokens.length);
	},
	addTokens: function(tokens) {
		this._super(tokens);
		this.updateTitleCount(this.tokens.length);
	}
});

ltpoc.SuggestedTagBox = ltpoc.TagBox.extend({
	init: function(id_tag, title) {
		this._super('suggested_tags', 'Suggested');
	},
	// overriding because we want to show the total number, not the filtered number
	removeToken: function(token) {
		this._super(token);
		this.updateTitleCount(ltpoc.tagManager.suggestedTags.length);
	},
	addToken: function(token) {
		this._super(token);
		this.updateTitleCount(ltpoc.tagManager.suggestedTags.length);
	},
	addTokens: function(tokens) {
		this._super(tokens);
		this.updateTitleCount(ltpoc.tagManager.suggestedTags.length);
	},
	
	getListClass: function() {
		return 'cf_tokenbox list';
	},
	
	handleDrop: function(tokens) {
		jQuery.each(tokens, function(i, tagToken) {
			ltpoc.tagManager.putTagInSuggested(tagToken.tag, 'user');
		});
	},
	
	canAcceptDrop: function() {
		var ok = this._super();
		if (ok) {
			var payload = cf.tokenManager.tokensBeingDragged();
			jQuery.each(payload, function(i, token) {
				if (!token.tag || token.tag.isUserGenerated()) {
					ok = false;
				}
			});				
		}
		return ok;
	},
	wasInsertedIntoDOM: function() {
		var poppet = this;
		jQuery('#cf_tokenbox_header_' + this.id_tag).click(function() {
			ltpoc.tagManager.toggleSuggestedIgnoredBuckets();
		}).hover(function() { jQuery(this).addClass('highlight'); }, function() { jQuery(this).removeClass('highlight'); });
		jQuery('#ltpoc_add_all').click( function() { ltpoc.tagManager.putAllSuggestedInCurrent(); return false; });
		
		jQuery('#ltpoc_tag_filter_select').change(function() {
			ltpoc.tagManager.filterSuggestedByType(jQuery('#ltpoc_tag_filter_select option:selected').attr('label'));
		});
	},
	getContainerHTML: function() {
		return this._super().replace('</h4>', 
			'</h4>\
			<div id="ltpoc_tag_filter_wrap">\
				<span id="ltpoc_filter_tags_form">\
					<span id="ltpoc_add_all"><a href="#">Add All</a></span>\
					<select id="ltpoc_tag_filter_select">\
						<option label="All">All</option>\
					</select>\
				</span>\
			</div>');
	}
});


ltpoc.CurrentTagBox = ltpoc.TagBox.extend({
	init: function(id_tag, title) {
		this._super('current_tags', 'Post Tags');
	},
	
	getListClass: function() {
		return 'cf_tokenbox cloud';
	},
	
	handleDrop: function(tokens) {
		jQuery.each(tokens, function(i, tagToken) {
			ltpoc.tagManager.putTagInCurrent(tagToken.tag, 'user');
		});
	}	
});

ltpoc.BlacklistedTagBox = ltpoc.TagBox.extend({
	init: function(id_tag, title) {
		this._super('blacklisted_tags', 'Ignored');
	},
	getListClass: function() {
		return 'cf_tokenbox list';
	},
	
	handleDrop: function(tokens) {
		jQuery.each(tokens, function(i, tagToken) {
			ltpoc.tagManager.putTagInBlacklist(tagToken.tag, 'user');
		});
	},

	canAcceptDrop: function() {
		var ok = this._super();
		if (ok) {
			var payload = cf.tokenManager.tokensBeingDragged();
			jQuery.each(payload, function(i, token) {
				// if the tag has an entity, it was suggested
				if (!token.tag || !token.tag.source) {
					ok = false;
				}
			});				
		}
		return ok;
	},
		
	wasInsertedIntoDOM: function() {
		var poppet = this;
		this.jq.slideUp(0);
		jQuery('#cf_tokenbox_header_' + this.id_tag).click(function() {
			ltpoc.tagManager.toggleSuggestedIgnoredBuckets();
		}).hover(function() { jQuery(this).addClass('highlight'); }, function() { jQuery(this).removeClass('highlight'); });
	},
	
	hidden: true
});