BX.namespace('iTrack.Crm.DetailEditorExt');

BX.iTrack.Crm.DetailEditorExt = {
    contactsData: {},
    init: function() {
        BX.addCustomEvent('BX.Crm.EntityEditorField:onLayout', BX.delegate(this.fieldLayoutHandler, this));
        //BX.addCustomEvent('BX.Crm.EntityEditorSection:onLayout', BX.delegate(this.detailHandler, this));
    },
    fieldLayoutHandler: function(field) {
        if(typeof field === 'object') {
            if(field.hasOwnProperty('_id')) {
                if(field._id === 'UF_CRM_1582660776') {
                    if(field._mode === 1) {
                        var $node = $(field._innerWrapper).find('select[name="UF_CRM_1582660776"]');
                        $node.select2();
                        $node.on('select2:select',function(){
                            field.onChange();
                            //$node[0].dispatchEvent(new Event('bxchange'));
                        });
                    }
                }
                if(field._id === 'UF_CRM_1589294112') {
                    var contactId = BX.prop.getString(field.getValue(), "VALUE", "");
                    if(!this.contactsData.hasOwnProperty(contactId)) {
                        BX.ajax.runAction('itrack:custom.api.deal.getContactData', {
                            data: {
                                id: BX.prop.getString(field.getValue(), "VALUE", "")
                            }
                        }).then(function (response) {
                            if (response.hasOwnProperty('status')) {
                                if (response.status === 'success' && response.hasOwnProperty('data')) {
                                    if (response.data.hasOwnProperty('CONTACT_DATA')) {

                                        this.contactsData[response.data.ID] = response.data.CONTACT_DATA;
                                        this.renderContactCommunicationButtons(field, response.data.CONTACT_DATA);
                                    }
                                }
                            }
                        }.bind(this), function (reason) {

                        }.bind(this));
                    } else {
                        this.renderContactCommunicationButtons(field, this.contactsData[contactId]);
                    }
                }
            }
        }
    },
    renderContactCommunicationButtons: function(field, data) {
        if(field && data) {
            var commTypes = ["PHONE", "EMAIL", "IM"];
            var info = BX.CrmEntityInfo.create(data);
            var buttonWrapper = BX.create("div",
                {props: {className: "crm-entity-widget-client-actions-container"}}
            );
            field._innerWrapper.appendChild(buttonWrapper);
            console.log(field._editor.getEntityTypeId());
            console.log(field._editor.getEntityId());
            for (var i = 0, j = commTypes.length; i < j; i++) {
                var commType = commTypes[i];
                var button = BX.Crm.ClientEditorCommunicationButton.create(
                    field._id + "_" + commType,
                    {
                        entityInfo: info,
                        type: commType,
                        ownerTypeId: field._editor.getEntityTypeId(),
                        ownerId: field._editor.getEntityId(),
                        container: buttonWrapper
                    }
                );
                button.layout();
            }

            var phones = info.getPhones();
            var emails = info.getEmails();
            if (phones.length > 0 || emails.length > 0) {
                var communicationContainer = BX.create("div", {props: {className: "crm-entity-widget-client-contact"}});
                field._innerWrapper.appendChild(communicationContainer);

                if (phones.length > 0) {
                    communicationContainer.appendChild(
                        BX.create("div",
                            {
                                props: {className: "crm-entity-widget-client-contact-item crm-entity-widget-client-contact-phone"},
                                //HACK: Disable autodetection of phone number for Microsoft Edge
                                attrs: {"x-ms-format-detection": "none"},
                                text: phones[0]["VALUE_FORMATTED"]
                            }
                        )
                    );
                }

                if (emails.length > 0) {
                    communicationContainer.appendChild(
                        BX.create("div",
                            {
                                props: {className: "crm-entity-widget-client-contact-item crm-entity-widget-client-contact-email"},
                                text: emails[0]["VALUE_FORMATTED"]
                            }
                        )
                    );
                }
            }
        }
    }
    /*detailHandler: function(editor, data) {
        $(editor._contentContainer).find('[name="UF_CRM_1582660776"]').select2();
    }*/
};