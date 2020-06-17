
BX.namespace('iTrack.Crm.Tasks.Kanban');

BX.iTrack.Crm.Tasks.Kanban = {
    grid: null,
    processedItems: {},
    init: function() {
        if(!!BX.Tasks && !!BX.Tasks.Kanban) {
            BX.addCustomEvent('Kanban.Grid:onRender', BX.delegate(this.renderHandler, this));
            BX.addCustomEvent('Kanban.Column:render', BX.delegate(this.columnRenderdHandler, this));
        }
    },
    renderHandler: function(grid) {
        this.grid = grid;
    },
    columnRenderdHandler: function(column) {
        if(this.grid === null) {
            this.grid = column.grid;
        }
        var items = column.items;
        var itemsIDs = [];
        for(var i in items) {
            /*
            if(items[i].data.hasOwnProperty('date_deadline') && items[i].data.date_deadline !== null) {
                if(items[i].data.hasOwnProperty('date_deadline_parse')) {
                    if(items[i].date_deadline) {

                        var newVal = items[i].date_deadline.innerText;
                        if(newVal.indexOf(':') < 0) {
                            newVal = newVal + ' ' + items[i].data.date_deadline_parse.HH + ':' + items[i].data.date_deadline_parse.MI;
                            items[i].date_deadline.innerText = newVal;
                        }


                    }
                } else {
                    items[i].date_deadline.innerText = items[i].data.date_deadline;
                }
            }*/

            itemsIDs.push(items[i].id);
        }

        if(itemsIDs.length) {
            this.requestItems(itemsIDs);
        }
    },
    requestItems: function(ids) {
        ids = ids || [];
        if(ids.length) {
            var postData = [];
            for(var i in ids) {
                if(!this.processedItems.hasOwnProperty(ids[i])) {
                    postData.push(ids[i]);
                    this.processedItems[ids[i]] = {data: '', description: '', attached: false};
                }
            }

            if(postData.length) {
                BX.ajax.runAction('itrack:custom.api.tasks.getAdditionalData', {
                    data: {
                        ids: postData
                    }
                }).then(function(response){
                    if(response.hasOwnProperty('status')) {
                        if(response.status === 'success') {
                            if(response.data.hasOwnProperty('items') && response.data.items.length) {
                                this.processItems(response.data.items);
                            }
                        }
                    }
                }.bind(this), function(error){

                });
            } else {
                this.renderAdditionalFields();
            }
        }
    },
    processItems: function(items) {
        items = items || [];
        for(var i in items) {
            if(items[i].hasOwnProperty('crmLink')) {
                this.processedItems[items[i].id].data = items[i].crmLink;
            }
            if(items[i].hasOwnProperty('description')) {
                this.processedItems[items[i].id].description = items[i].description;
            }
        }
        this.renderAdditionalFields();
    },
    addCrmLink: function(id, data) {
        this.processedItems[id].data = data;
    },
    renderAdditionalFields: function() {
        for(var i in this.processedItems) {
            if(this.grid.items[i]) {
                if(this.processedItems[i].data.length) {
                    if(this.grid.items[i].task_content) {
                        if (!this.grid.items[i].task_content.querySelector('.itrack-custom-crm-taskskanban__additional-fields')) {
                            var crmLink = BX.create('div', {
                                attrs: {className: 'itrack-custom-crm-taskskanban__additional-fields'},
                                html: this.processedItems[i].data
                            });
                            crmLink.querySelectorAll('a').forEach(function (node) {
                                BX.bind(node, 'click', BX.delegate(this.onCrmLinkClick, this));
                            }.bind(this));
                            BX.append(crmLink, this.grid.items[i].task_content);
                        }
                        this.grid.items[i].task_content.style.display = 'block';
                    }
                }

                if(this.grid.items[i].container) {
                    if (this.processedItems[i].description.length) {
                        if (!this.grid.items[i].container.querySelector('.itrack-custom-crm-taskskanban__additional-fields__description')) {
                            var descr = BX.create('div', {
                                attrs: {className: 'itrack-custom-crm-taskskanban__additional-fields__description'},
                                html: this.processedItems[i].description
                            });
                            BX.insertAfter(descr, this.grid.items[i].link);
                        }
                    }
                }
            }
        }
    },
    onCrmLinkClick: function(event) {
        console.log(event);
        event.stopPropagation();
    }
}

BX.iTrack.Crm.Tasks.Kanban.init();