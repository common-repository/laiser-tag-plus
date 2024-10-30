
ltpoc.mcePlugin = {
	getInfo: function() {
		return {
			longname : 'Laiser Tag Plus TinyMCE Plugin',
			author : 'PCIS',
			authorurl : 'http://www.pcis.com/laiser-tag',
			infourl : '',
			version : "1.0"
		};
	},
	initInstance: function(instance) {
		ltpoc.rte = instance;
	},
	removeInstance: function(instance) {
		ltpoc.rte = null;
	},
	onChange: function(inst) {
		ltpoc.tickleIdleTimer();
	},
	handleEvent: function(event) {
		ltpoc.tickleIdleTimer();
		return true;
	},
	execCommand: function(editorID, element, command, userInterface, value) {
		return false;
	},
	cleanup : function(type, content) {
		switch (type) {
			case 'insert_to_editor':
				this.onChange(ltpoc.rte);
			break;
			case 'get_from_editor':
			break;
		}
		return content;
	}	
};
if (ltpoc && tinyMCE) {
	tinyMCE.addPlugin('laisertagplus', ltpoc.mcePlugin);
}

