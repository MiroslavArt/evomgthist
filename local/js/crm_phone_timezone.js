
BX.namespace('iTrack.Crm.PhoneTimezone');

BX.iTrack.Crm.PhoneTimezone = {
    kanban: null,
    init: function(type) {
        switch(type) {
            case 'detail':
                BX.addCustomEvent('BX.Crm.EntityEditor:onInit', BX.delegate(this.detailHandler, this));
                BX.addCustomEvent('BX.Crm.EntityEditorSection:onLayout', BX.delegate(this.detailHandler, this));
                break;
            case 'kanban':
                BX.addCustomEvent('Kanban.Grid:onRender', BX.delegate(this.kanbanHandler, this));
                break;
        }
    },
    detailHandler: function(editor, data) {
        document.querySelectorAll('.crm-entity-widget-client-contact-phone').forEach(function (el) {
            this.processPhone(el);
        }.bind(this));
        document.querySelectorAll('.crm-entity-phone-number').forEach(function (el) {
            this.processPhone(el);
        }.bind(this));
    },
    kanbanHandler: function(grid){
        this.kanban = grid;
        var collectPhones = [];
        for(var i in grid.items) {
            if(grid.items[i].data.hasOwnProperty('phone')) {
                if (grid.items[i].data.phone.length) {
                    var localValue = localStorage.getItem(grid.items[i].data.phone[0].value);
                    if (localValue == null) {
                        collectPhones.push(grid.items[i].data.phone[0].value);
                    }
                }
            }
        }
        if(collectPhones.length) {
            this.requestTimezoneCollection(collectPhones).then(function(response) {
                console.log(response);
                this.processCollectionResponse(response);
                this.processKanbanPhones();
            }.bind(this), function(error){
                console.log(error);
            }.bind(this));
        } else {
            this.processKanbanPhones();
        }
    },
    processKanbanPhones: function() {
        var items = this.kanban.items;
        for(var i in items) {
            if(items[i].data.hasOwnProperty('phone')) {
                if (items[i].data.phone.length) {
                    var localValue = localStorage.getItem(items[i].data.phone[0].value);
                    if (localValue !== null) {
                        if (!items[i].contactBlock.querySelector('.itrack-custom-crm-phonetime__phone-block')) {
                            var timeNode = this.createTimeNodeForContactBlock();
                            BX.append(timeNode, items[i].contactBlock);
                            new BX.iTrack.Crm.PhoneTimezone.Timer(localValue, timeNode);
                        }
                    }
                }
            }
        }
    },
    processPhone: function(phoneNode) {
        phoneNode = phoneNode || null;
        if(phoneNode) {
            var container = phoneNode.parentNode.parentNode;
            if(!container.querySelector('.itrack-custom-crm-phonetime__phone-block')) {
                var phone = phoneNode.innerText;
                var localValue = localStorage.getItem(phone);
                var timeNode = this.createTimeNodeForContactBlock();
                if(localValue !== null) {
                    BX.append(timeNode, container);
                    new BX.iTrack.Crm.PhoneTimezone.Timer(localValue, timeNode);
                } else {
                    this.requestTimezone(phone).then(function(response){
                        var timeZone = this.processResponse(response);
                        localStorage.setItem(phone, timeZone);
                        BX.append(timeNode, container);
                        new BX.iTrack.Crm.PhoneTimezone.Timer(timeZone, timeNode);
                    }.bind(this), function(error){
                        console.log(error);
                    }.bind(this));
                }
            }
        }
    },
    createTimeNodeForContactBlock: function() {
        return BX.create('div', {
            attrs: {className: 'itrack-custom-crm-phonetime__phone-block'},
            children: [BX.create('span', {attrs:{className: 'itrack-custom-crm-phonetime__phone-block_text'}})]
        });
    },
    requestTimezone: function(phone) {
        return BX.ajax.runAction('itrack:custom.api.phone.getTimezone', {
            data: {
                phone: phone
            }
        });
    },
    processResponse: function(response) {
        var timezone = 'Europe/Moscow';
        if(response.hasOwnProperty('status')) {
            if(response.status == 'success') {
                if(response.data.length) {
                    timezone = response.data;
                }
            }
        }
        return timezone;
    },
    requestTimezoneCollection: function (phones) {
        return BX.ajax.runAction('itrack:custom.api.phone.getTimezoneCollection', {
            data: {
                phones: phones
            }
        });
    },
    processCollectionResponse: function(response) {
        console.log(response);
        if(response.hasOwnProperty('status')) {
            if(response.status == 'success') {
                if(response.data.length) {
                    for(var i in response.data) {
                        localStorage.setItem(response.data[i].phone, response.data[i].timezone);
                    }
                }
            }
        }
    }
};

BX.namespace('iTrack.Crm.PhoneTimezone.Timer');

BX.iTrack.Crm.PhoneTimezone.Timer = function(timezone, node) {
    this.timezone = timezone;
    this.node = node;
    this.init();
};

BX.iTrack.Crm.PhoneTimezone.Timer.prototype.init = function() {
    setInterval(BX.delegate(this.updateTime, this), 15000);
    this.updateTime();
};

BX.iTrack.Crm.PhoneTimezone.Timer.prototype.updateTime = function() {
    var now = moment();
    now.tz(this.timezone);
    this.node.querySelector('span').innerText = now.format('HH:mm');
};