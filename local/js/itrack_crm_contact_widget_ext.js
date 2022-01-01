BX.namespace('iTrack.Crm.ContactWidgetExt');

BX.iTrack.Crm.ContactWidgetExt = {
    init: function() {
        BX.addCustomEvent('BX.Crm.EntityEditor:onInit', BX.delegate(this.detailHandler, this));
        BX.addCustomEvent('BX.Crm.EntityEditorSection:onLayout', BX.delegate(this.detailHandler, this));
    },
    detailHandler: function(editor, data) {
        //console.log('editor', editor);
        //console.log('data', data);
        
        var widgetNode = document.querySelector('.crm-entity-widget-client-contact');
        if(widgetNode) {
            for(var i in editor._fields) {
                var field = editor._fields[i];
                if(field._id === 'CLIENT') {
                    for(var p in field._contactPanels) {
                        var entityInfo = field._contactPanels[p]._entityInfo;
                        var phones = entityInfo.getPhones();
                        var emails = entityInfo.getEmails();
                        if(phones.length > 1 || emails.length > 1) {
                            var additionalData = BX.create("div", {
                                props: {className: "itrack-crm-contact-widget-ext-block"}
                            });
                            if(phones.length > 1) {
                                for (var j in phones) {
                                    if (j == 0) {
                                        continue;
                                    }
                                    additionalData.appendChild(
                                        BX.create("div",
                                            {
                                                props: {className: "crm-entity-widget-client-contact-item crm-entity-widget-client-contact"},
                                                //HACK: Disable autodetection of phone number for Microsoft Edge
                                                attrs: {"x-ms-format-detection": "none"},
                                                text: phones[j]["VALUE_FORMATTED"]
                                            }
                                        )
                                    );
                                }
                            }
                            if(emails.length > 1) {
                                for (var j in emails) {
                                    if (j == 0) {
                                        continue;
                                    }
                                    additionalData.appendChild(
                                        BX.create("div",
                                            {
                                                props: {className: "crm-entity-widget-client-contact-item crm-entity-widget-client-contact"},
                                                //HACK: Disable autodetection of phone number for Microsoft Edge
                                                attrs: {"x-ms-format-detection": "none"},
                                                text: emails[j]["VALUE_FORMATTED"]
                                            }
                                        )
                                    );
                                }
                            }
                            widgetNode.parentNode.appendChild(additionalData);
                        }
                    }
                }
            }
        }
    }
};
