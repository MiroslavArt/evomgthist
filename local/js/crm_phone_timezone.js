
BX.namespace('iTrack.Crm.PhoneTimezone');

BX.iTrack.Crm.PhoneTimezone = {
    init: function(type) {
        switch(type) {
            case 'detail':
                BX.addCustomEvent('BX.Crm.EntityEditor:onInit', BX.delegate(this.detailHandler, this));
                BX.addCustomEvent('BX.Crm.EntityEditorSection:onLayout', BX.delegate(this.detailHandler, this));
                break;
            case 'canban':
                BX.addCustomEvent('Kanban.Grid:onRender', BX.delegate(this.kanbanHandler, this));
                break;
        }
    },
    detailHandler: function(editor, data) {
        /*var container = document.querySelector('.crm-entity-widget-content-block[data-cid="CONTACT"]');
        if(container) {*/
            document.querySelectorAll('.crm-entity-widget-client-contact-phone').forEach(function (el) {
                this.processPhone(el);
            }.bind(this));
        //}
        document.querySelectorAll('.crm-entity-phone-number').forEach(function (el) {
            this.processPhone(el);
        }.bind(this));
        /*data.model._data
        console.log('detail handler');
        console.log(event);*/
    },
    kanbanHandler: function(grid){
        //grid.items[i].data.phone[0].value
        //grid.items[i].contactBlock
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