define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui"
],
function (dojo, declare) {
    return declare("bgagame.bgacats", ebg.core.gamegui, {
        constructor: function(){
            this.hand = [];
            this.gamedatas = null;
            this._pending = {};
        },

        setup: function( gamedatas ) {
            this.gamedatas = gamedatas;
            this.player_id = this.player_id || this.getCurrentPlayerId();

            // Build hand
            this._renderHand(gamedatas.hand);

            // Build players
            for (var pid in gamedatas.players) {
                this._renderZone('herd_'+pid, gamedatas.herds[pid], false);
                this._renderZone('herdup_'+pid, gamedatas.herd_up[pid], true);
                this._renderZone('discard_'+pid, gamedatas.discards[pid], true, true);
            }

            // Connect notifs
            this._setupNotifications();
        },

        onEnteringState: function(stateName, args) {
            if (stateName == 'playerDeclare' && this.isCurrentPlayerActive()) {
                this._showDeclareUI();
            }
            if (stateName == 'challengeWindow') {
                this._showChallengeUI();
            }
            if (stateName == 'interceptChallenge') {
                this._showInterceptChallengeUI();
            }
            if (stateName == 'selectTarget' && this.isCurrentPlayerActive()) {
                this._showTargetUI(args.args);
            }
            if (stateName == 'interceptDecision' && this.isCurrentPlayerActive()) {
                this._showInterceptUI(args.args);
            }
            if (stateName == 'bluffPenaltyPick' && this.isCurrentPlayerActive()) {
                this._showBlindPickUI(args.args);
            }
            if (stateName == 'truthPenaltyPick' && this.isCurrentPlayerActive()) {
                this._showBlindPickUI(args.args);
            }
        },

        onLeavingState: function(stateName) {
            this._clearUI();
        },

        onUpdateActionButtons: function(stateName, args) {
            // No global buttons needed; UI panels render dedicated controls
        },

        _renderHand: function(cards) {
            var node = $('hand-area');
            dojo.empty(node);
            cards.sort(function(a,b){ return a.location_arg - b.location_arg; });
            this.hand = cards;
            for (var i=0;i<cards.length;i++) {
                var c = cards[i];
                var div = dojo.create('div', { id:'hand_'+c.id, 'class':'card facedown', innerHTML:'Hand' }, node);
                dojo.addClass(div, 'clickable');
                // Capture card id correctly to avoid binding the last value
                (function(self, cid){
                    dojo.connect(div, 'onclick', function(){ self._onClickHandCard(cid); });
                })(this, c.id);
            }
        },

        _renderZone: function(zoneId, cards, faceup, discard){
            var node = $(zoneId);
            if (!node) return;
            dojo.empty(node);
            for (var i=0;i<cards.length;i++) {
                var c = cards[i];
                var div = dojo.create('div', { id:zoneId+'_card_'+c.id, 'class':'card '+(faceup?'faceup':'facedown')+(discard?' discard':'') }, node);
                div.innerHTML = faceup ? this._typeToText(c.type_arg>0?c.type_arg:c.type) : '';
            }
        },

        _typeToText: function(type){
            var map = {1:'Kitten',2:'Show Cat',3:'Alley Cat',4:'Catnip',5:'Animal Control',6:'Laser Pointer'};
            return map[type] || '?';
        },

        _showDeclareUI: function(){
            var panel = $('decl-area'); dojo.empty(panel);
            dojo.create('div', { innerHTML: _('Choose a hand card, pick a declaration and (if needed) a target player.') }, panel);
            var decls = [
                {t:1,n:_('Kitten')},{t:2,n:_('Show Cat')},{t:3,n:_('Alley Cat')},
                {t:4,n:_('Catnip')},{t:5,n:_('Animal Control')},{t:6,n:_('Laser Pointer')}
            ];
            var self=this;
            decls.forEach(function(d){
                var btn = dojo.create('button', { 'class':'bga-btn', innerHTML:d.n }, panel);
                dojo.connect(btn, 'onclick', function(){
                    self._pending.decl = d.t;
                    self.showMessage(_('Click a hand card to play and declare ')+d.n, 'info');
                });
            });
            // Target player selection is prompted after clicking "Declare" with a targeted type
        },

        _onClickHandCard: function(card_id){
            if (!this._pending.decl) { this.showMessage(_('Choose a declaration first'), 'error'); return; }
            var decl = this._pending.decl;
            var tgtZone = (decl==3||decl==4)?1: (decl==5?2:0);
            this._pending.card_id = card_id;
            this._pending.tgtZone = tgtZone;
            // Always let the server drive the flow
            this.ajaxcall('/bgacats/bgacats/actDeclarePlay.html', {
                card_id: card_id, declared_type: decl, target_player_id: 0
            }, this, function(){}, function(){});
        },

        _renderOpponentRows: function(opponents, zone){
            var frag = document.createDocumentFragment();
            var self=this;
            Object.keys(opponents).forEach(function(pid){
                var data = opponents[pid];
                var row = dojo.create('div', {'class':'target-row'}, frag);
                dojo.create('span', {'class':'target-name', innerHTML: data.name }, row);
                var btn = dojo.create('button', {'class':'bga-btn', innerHTML:_('Target')}, row);
                dojo.connect(btn, 'onclick', function(){
                    self.ajaxcall('/bgacats/bgacats/actSelectTargetPlayer.html', { target_player_id: pid }, self, function(){}, function(){});
                });
                var preview = dojo.create('div', {'class':'target-preview'}, row);
                if (zone==1) {
                    for (var i=0;i<(data.handSize||0);i++) dojo.create('div', {'class':'card facedown', innerHTML:''}, preview);
                } else if (zone==2) {
                    for (var i=0;i<(data.herdCount||0);i++) dojo.create('div', {'class':'card facedown', innerHTML:''}, preview);
                }
            });
            return frag;
        },

        _showChallengeUI: function(){
            var panel = $('challenge-area'); dojo.empty(panel);
            dojo.create('div',{innerHTML:_('Challenge the claim or pass.')}, panel);
            var self=this;
            this.addActionButton('btnChallenge', _('Challenge'), function(){
                self.ajaxcall('/bgacats/bgacats/actChallenge.html', {}, self, function(){}, function(){});
            });
            this.addActionButton('btnPass', _('Pass'), function(){
                self.ajaxcall('/bgacats/bgacats/actPassChallenge.html', {}, self, function(){}, function(){});
            });
        },

        _showInterceptChallengeUI: function(){
            var panel = $('challenge-area'); dojo.empty(panel);
            dojo.create('div',{innerHTML:_('Challenge the intercept or pass.')}, panel);
            var self=this;
            this.addActionButton('btnChallengeIntercept', _('Challenge'), function(){
                self.ajaxcall('/bgacats/bgacats/actChallengeIntercept.html', {}, self, function(){}, function(){});
            });
            this.addActionButton('btnPassIntercept', _('Pass'), function(){
                self.ajaxcall('/bgacats/bgacats/actPassIntercept.html', {}, self, function(){}, function(){});
            });
        },

        _showTargetUI: function(args){
            var panel = $('target-area'); dojo.empty(panel);
            if (!args) return;
            if (args.targetPlayer == 0) {
                dojo.create('div',{innerHTML:_('Select a target player:')}, panel);
                panel.appendChild(this._renderOpponentRows(args.opponents || {}, args.zone));
                return;
            }
            if (args.zone == 1) {
                dojo.create('div',{innerHTML:_('Select a slot in the target hand.')}, panel);
                for (var i=1;i<=args.handSize;i++) {
                    (function(idx){
                        var btn = dojo.create('button', {'class':'bga-btn', innerHTML:_('Slot ')+idx}, panel);
                        dojo.connect(btn, 'onclick', function(){ 
                            this.ajaxcall('/bgacats/bgacats/actSelectHandSlot.html', { target_player_id: args.targetPlayer, slot_index: idx }, this, function(){}, function(){});
                        }.bind(this));
                    }).call(this,i);
                }
            } else if (args.zone == 2) {
                dojo.create('div',{innerHTML:_('Select a face-down herd card.')}, panel);
                var ids = args.herdCards || [];
                var self=this;
                ids.forEach(function(cid){
                    var el = $('herd_'+args.targetPlayer+'_card_'+cid);
                    if (el) {
                        dojo.addClass(el, 'clickable');
                        dojo.connect(el, 'onclick', function(){
                            self.ajaxcall('/bgacats/bgacats/actSelectHerdCard.html', { target_player_id: args.targetPlayer, card_id: cid }, self, function(){}, function(){});
                        });
                    }
                });
            }
        },

        _showInterceptUI: function(args){
            var panel = $('intercept-area'); dojo.empty(panel);
            dojo.create('div',{innerHTML:_('Declare Laser Pointer intercept?')}, panel);
            var self=this;
            this.addActionButton('btnNoIntercept', _('No'), function(){
                self.ajaxcall('/bgacats/bgacats/actDeclineIntercept.html', {}, self, function(){}, function(){});
            });
            this.addActionButton('btnYesIntercept', _('Yes'), function(){
                self.ajaxcall('/bgacats/bgacats/actDeclareIntercept.html', { zone: args.allowedZone }, self, function(){}, function(){});
            });
        },

        _showBlindPickUI: function(args){
            var panel = $('target-area'); dojo.empty(panel);
            dojo.create('div',{innerHTML:_('Pick a blind slot from the target hand.')}, panel);
            var n = args.handSize || 0;
            for (var i=1;i<=n;i++) {
                (function(idx){
                    var btn = dojo.create('button', {'class':'bga-btn', innerHTML:_('Slot ')+idx}, panel);
                    dojo.connect(btn, 'onclick', function(){ 
                        this.ajaxcall('/bgacats/bgacats/actPickBlindFromHand.html', { target_player_id: args.targetPlayer, slot_index: idx }, this, function(){}, function(){});
                    }.bind(this));
                }).call(this,i);
            }
        },

        _clearUI: function(){
            ['decl-area','challenge-area','target-area','intercept-area'].forEach(function(id){ var n=$(id); if (n) dojo.empty(n); });
            dojo.query('.card.clickable').removeClass('clickable');
        },

        // Notifications
        _setupNotifications: function(){
            dojo.subscribe('declarePlay', this, function(notif){});
            dojo.subscribe('targetChosen', this, function(notif){});
            dojo.subscribe('challengeMade', this, function(notif){});
            dojo.subscribe('revealPlayed', this, function(notif){ this.showMessage(_('Played card was ')+notif.args.printed, 'info'); }.bind(this));
            dojo.subscribe('revealHandCard', this, function(notif){ this.showMessage(_('Revealed ')+notif.args.card, 'info'); }.bind(this));
            dojo.subscribe('revealHerdCard', this, function(notif){ this.showMessage(_('Revealed ')+notif.args.card, 'info'); }.bind(this));
            dojo.subscribe('addToHerd', this, function(notif){ this.showMessage(_('A card was added to herd as ')+notif.args.decl, 'info'); }.bind(this));
            dojo.subscribe('scorePlayer', this, function(notif){ this.showMessage(_(notif.args.player_name+' scores '+notif.args.score), 'info'); }.bind(this));
        },
    });
});