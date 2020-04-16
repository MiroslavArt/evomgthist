
BX.namespace('iTrack.Crm.Tasks.Kanban');

BX.iTrack.Crm.Tasks.Kanban = {
    grid: null,
    processedItems: {},
    init: function() {
        console.log('init');
        BX.addCustomEvent('Kanban.Grid:onRender', BX.delegate(this.renderHandler, this));
        BX.addCustomEvent('Kanban.Column:render', BX.delegate(this.columnRenderdHandler, this));
    },
    renderHandler: function(grid) {
        this.grid = grid;
    },
    columnRenderdHandler: function(column) {
        this.grid = column.grid;
        var items = column.items;
        var itemsIDs = [];
        for(var i in items) {
            if(items[i].data.hasOwnProperty('date_deadline')) {
                var deadline = items[i].data.date_deadline;
                if(parseInt(deadline) > 0) {
                    var deadlineDate = new Date(parseInt(deadline) * 1000);
                    if(items[i].date_deadline) {
                        var newVal = items[i].date_deadline.innerText;
                        if(newVal.indexOf(':') < 0) {
                            var hours = ('0'+deadlineDate.getHours()).slice(-2);
                            var minutes = ('0'+deadlineDate.getMinutes()).slice(-2);
                            newVal = newVal + ' ' + hours + ':' + minutes;
                            items[i].date_deadline.innerText = newVal;
                        }
                    }
                }
            }

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
                    if(!this.grid.items[i].task_content.querySelector('.itrack-custom-crm-taskskanban__additional-fields')) {
                        var crmLink = BX.create('div', {
                            attrs: {className: 'itrack-custom-crm-taskskanban__additional-fields'},
                            html: this.processedItems[i].data
                        });
                        crmLink.querySelectorAll('a').forEach(function(node){
                            BX.bind(node, 'click', BX.delegate(this.onCrmLinkClick, this));
                        }.bind(this));
                        BX.append(crmLink, this.grid.items[i].task_content);
                    }
                    this.grid.items[i].task_content.style.display = 'block';
                }

                if(this.processedItems[i].description.length) {
                    if(!this.grid.items[i].container.querySelector('.itrack-custom-crm-taskskanban__additional-fields__description')) {
                        var descr = BX.create('div', {
                            attrs: {className: 'itrack-custom-crm-taskskanban__additional-fields__description'},
                            html: this.processedItems[i].description
                        });
                        BX.insertAfter(descr, this.grid.items[i].link);
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