/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * HerdingCats implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * herdingcats.js
 *
 * HerdingCats user interface script
 * 
 * Complete JavaScript game client for Herding Cats bluffing card game
 *
 */

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter",
    "ebg/stock"
],
function (dojo, declare) {
    return declare("bgagame.herdingcats", ebg.core.gamegui, {
        constructor: function(){
            console.log('HerdingCats constructor');
              
            // Card Type Constants
            this.CARD_TYPE_KITTEN = 1;
            this.CARD_TYPE_SHOWCAT = 2;  
            this.CARD_TYPE_ALLEYCAT = 3;
            this.CARD_TYPE_CATNIP = 4;
            this.CARD_TYPE_ANIMALCONTROL = 5;
            this.CARD_TYPE_LASERPOINTER = 6;

            // Stock components
            this.playerHand = null;
            this.playerHerds = {};
            this.playerDiscards = {};
            
            // Game state tracking
            this.selectedCard = null;
            this.currentDeclaration = null;
            this.targetSelectionActive = false;
            
            // Card type names for UI
            this.cardTypeNames = {  
                1: _('Kitten'),
                2: _('Show Cat'),
                3: _('Alley Cat'),
                4: _('Catnip'),
                5: _('Animal Control'),
                6: _('Laser Pointer')
            };
        },

        // Build action URL dynamically based on current game slug
        _actionUrl: function(action) {
            return "/" + this.game_name + "/" + this.game_name + "/" + action + ".html";
        },

        // Lightweight client->server log for Studio debugging
        _log: function(tag) {
            try {
                this.ajaxcall(this._actionUrl("actClientLog"), { level: 'info', msg: String(tag).replace(/[^a-zA-Z0-9_]/g,'_') }, this, function(){});
            } catch(e) {}
        },

        // Normalize BGA onEnteringState args envelope to the inner args object
        _stateArgs: function(envelope) {
            if (envelope && typeof envelope === 'object' && 'args' in envelope) {
                return envelope.args || {};
            }
            return envelope || {};
        },
        
        /*
            setup:
            
            This method must set up the game user interface according to current game situation specified
            in parameters.
            
            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)
            
            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */
        
        setup: function( gamedatas )
        {
            console.log( "Starting game setup", gamedatas );

            // Store game data
            this.gamedatas = gamedatas;

            // Setup player hand
            this.setupPlayerHand(gamedatas);
            
            // Setup player boards
            this.setupPlayerBoards(gamedatas);
            
            // Initialize counters
            this.setupCounters(gamedatas);
            
            // Load current state
            this.loadGameState(gamedatas);
            
            // Setup event handlers
            this.setupEventHandlers();
            
            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            console.log( "Ending game setup" );
        },

        setupPlayerHand: function(gamedatas) {
            // Create stock component for current player hand
            this.playerHand = new ebg.stock();
            this.playerHand.create(this, $('hc_current_hand'), 72, 96);
            this.playerHand.image_items_per_row = 1;
            this.playerHand.centerItems = true;
            
            // Add all card types to stock using existing JPEG art
            const typeToImg = {
                1: 'img/herding_cats_art/kitten.jpeg',
                2: 'img/herding_cats_art/showcat.jpeg',
                3: 'img/herding_cats_art/alleycat.jpeg',
                4: 'img/herding_cats_art/catnip.jpeg',
                5: 'img/herding_cats_art/animalcontrol.jpeg',
                6: 'img/herding_cats_art/laserpointer.jpeg'
            };
            for (let cardType = 1; cardType <= 6; cardType++) {
                this.playerHand.addItemType(cardType, cardType, g_gamethemeurl + typeToImg[cardType], 0);
            }
            
            // Connect selection handler
            dojo.connect(this.playerHand, 'onChangeSelection', this, 'onPlayerHandSelectionChanged');
            
            // Load current player's hand
            if (gamedatas.hand) {
            // gamedatas.hand may be an object keyed by string ids or an array; support both
                const list = Array.isArray(gamedatas.hand) ? gamedatas.hand : Object.values(gamedatas.hand);
                // Add all cards to stock
                list.forEach(card => {
                    const cid = parseInt(card.id ?? card.card_id);
                    const ctype = parseInt(card.type ?? card.card_type);
                    if (!isNaN(cid) && !isNaN(ctype)) {
                        this.playerHand.addToStockWithId(ctype, cid);
                    }
                });
                // Reorder hand to match server-provided positions (location_arg) so both players see the same order
                try {
                    const weights = {};
                    for (let i = 0; i < list.length; i++) {
                        const card = list[i];
                        const cid = parseInt(card.id ?? card.card_id);
                        const pos = parseInt(card.location_arg ?? (i + 1));
                        if (!isNaN(cid) && !isNaN(pos)) weights[cid] = pos;
                    }
                    if (this.playerHand.changeItemsWeight) {
                        this.playerHand.changeItemsWeight(weights);
                    }
                } catch (e) { console.warn('hand weight order failed', e); }
            }
        },

        setupPlayerBoards: function(gamedatas) {
            // Initialize herd and discard stocks for each player
            for (let playerId in gamedatas.players) {
                // Create herd stocks (face-down and face-up)
                this.playerHerds[playerId] = {
                    faceDown: new ebg.stock(),
                    faceUp: new ebg.stock()
                };
                
                // Setup face-down herd
                this.playerHerds[playerId].faceDown.create(this, $('hc_herd_face_down_' + playerId), 72, 96);
                this.playerHerds[playerId].faceDown.image_items_per_row = 1;
                this.playerHerds[playerId].faceDown.addItemType(0, 0, g_gamethemeurl + 'img/herding_cats_art/cardback.jpeg', 0);
                
                // Setup face-up herd
                this.playerHerds[playerId].faceUp.create(this, $('hc_herd_face_up_' + playerId), 72, 96);
                this.playerHerds[playerId].faceUp.image_items_per_row = 1;
                const typeToImg = {
                    1: 'img/herding_cats_art/kitten.jpeg',
                    2: 'img/herding_cats_art/showcat.jpeg',
                    3: 'img/herding_cats_art/alleycat.jpeg',
                    4: 'img/herding_cats_art/catnip.jpeg',
                    5: 'img/herding_cats_art/animalcontrol.jpeg',
                    6: 'img/herding_cats_art/laserpointer.jpeg'
                };
                for (let cardType = 1; cardType <= 6; cardType++) {
                    this.playerHerds[playerId].faceUp.addItemType(cardType, cardType, g_gamethemeurl + typeToImg[cardType], 0);
                }
                
                // Create discard stock
                this.playerDiscards[playerId] = new ebg.stock();
                this.playerDiscards[playerId].create(this, $('hc_discard_' + playerId), 72, 96);
                this.playerDiscards[playerId].image_items_per_row = 1;
                const typeToImg2 = {
                    1: 'img/herding_cats_art/kitten.jpeg',
                    2: 'img/herding_cats_art/showcat.jpeg',
                    3: 'img/herding_cats_art/alleycat.jpeg',
                    4: 'img/herding_cats_art/catnip.jpeg',
                    5: 'img/herding_cats_art/animalcontrol.jpeg',
                    6: 'img/herding_cats_art/laserpointer.jpeg'
                };
                for (let cardType = 1; cardType <= 6; cardType++) {
                    this.playerDiscards[playerId].addItemType(cardType, cardType, g_gamethemeurl + typeToImg2[cardType], 0);
                }
                
                // Load existing cards
                if (gamedatas.herds && gamedatas.herds[playerId]) {
                    // Load face-down herd
                    if (gamedatas.herds[playerId].face_down) {
                        gamedatas.herds[playerId].face_down.forEach(card => {
                            this.playerHerds[playerId].faceDown.addToStockWithId(0, card.id);
                        });
                    }
                    
                    // Load face-up herd
                    if (gamedatas.herds[playerId].face_up) {
                        gamedatas.herds[playerId].face_up.forEach(card => {
                            this.playerHerds[playerId].faceUp.addToStockWithId(card.type, card.id);
                        });
                    }
                }
                
                // Load discard pile
                if (gamedatas.discards && gamedatas.discards[playerId]) {
                    gamedatas.discards[playerId].forEach(card => {
                        this.playerDiscards[playerId].addToStockWithId(card.type, card.id);
                    });
                }
            }
        },

        setupCounters: function(gamedatas) {
            // Setup hand count and score counters for each player
            for (let playerId in gamedatas.players) {
                // Update hand counts
                if (gamedatas.handCounts && gamedatas.handCounts[playerId] !== undefined) {
                    $('hc_hand_count_' + playerId).innerHTML = gamedatas.handCounts[playerId];
                }
                
                // Update scores
                if (gamedatas.scores && gamedatas.scores[playerId] !== undefined) {
                    $('hc_score_' + playerId).innerHTML = gamedatas.scores[playerId];
                }
            }
        },

        loadGameState: function(gamedatas) {
            // Update current action display based on game state
            if (gamedatas.gamestate) {
                const stateName = gamedatas.gamestate.name;
                this.updateActionPrompts(stateName, gamedatas.gamestate.args);
            }
        },

        setupEventHandlers: function() {
            // Connect card type declaration buttons
            dojo.query('.hc_card_type_btn').connect('onclick', this, 'onCardTypeDeclared');
            
            // Connect cancel buttons
            dojo.connect($('hc_cancel_declare'), 'onclick', this, 'onCancelDeclaration');
            dojo.connect($('hc_cancel_target'), 'onclick', this, 'onCancelTargeting');
        },

        ///////////////////////////////////////////////////
        //// Game & client states
        
        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //
        onEnteringState: function( stateName, args )
        {
            console.log( 'Entering state: '+stateName, args );
            this._log('enter_'+stateName);
            const a = this._stateArgs(args);
            
            switch( stateName )
            {
                case 'awaitDeclaration':
                    this.onEnteringState_awaitDeclaration(a);
                    break;
                    
                case 'challengeWindow':
                    this.onEnteringState_challengeWindow(a);
                    break;
                    
                case 'selectTarget':
                    this.onEnteringState_selectTarget(a);
                    break;
                    
                case 'interceptDeclare':
                    this.onEnteringState_interceptDeclare(a);
                    break;
                    
                case 'interceptChallengeWindow':
                    this.onEnteringState_interceptChallengeWindow(a);
                    break;
                    
                case 'challengerSelectBluffPenalty':
                case 'attackerSelectTruthfulPenalty':
                case 'interceptChallengerSelectPenalty':
                    this.onEnteringState_selectPenalty(a);
                    break;
                case 'alleyCatEffectSelect':
                    this.onEnteringState_selectPenalty(a);
                    break;
                case 'catnipEffectSelect':
                    this.onEnteringState_selectPenalty(a);
                    break;
                case 'animalControlEffectSelect':
                    this.onEnteringState_selectPenalty(a);
                    break;
            }
        },

        onEnteringState_awaitDeclaration: function(args) {
            if (this.isCurrentPlayerActive()) {
                this.updateActionPrompts('awaitDeclaration', args);
                // Enable hand card selection
                this.playerHand.setSelectionMode(1);
            }
        },

        onEnteringState_challengeWindow: function(args) {
            // Show challenge options for eligible players
            // Cache declared data so preview persists across re-renders
            if (args) {
                // Prefer numeric declared_type; fallback to declared_card
                const dType = (args.declared_type !== undefined && args.declared_type !== null)
                    ? args.declared_type
                    : args.declared_card;
                if (dType !== undefined && dType !== null) this.currentDeclaredType = dType;
                // Accept either actor_name or acting_player_name
                this.currentActorName = args.actor_name || args.acting_player_name || this.currentActorName;
            }
            this.updateActionPrompts('challengeWindow', args);
            // Ensure preview is rendered immediately
            const previewType = (args && (args.declared_type !== undefined || args.declared_card !== undefined))
                ? (args.declared_type !== undefined ? args.declared_type : args.declared_card)
                : this.currentDeclaredType;
            if (previewType !== undefined && previewType !== null) this.renderDeclaredPreview(previewType);
        },

        onEnteringState_selectTarget: function(args) {
            // Always update the yellow prompt for clarity
            this.updateActionPrompts('selectTarget', args);

            // Show selector for the acting player; be robust across engine quirks
            const isActor = args && (parseInt(args.acting_player_id) === parseInt(this.player_id));
            if (this.isCurrentPlayerActive() || isActor) {
                this._log('enter_selectTarget_show');
                this.showTargetSelection(args);
            } else {
                this._log('enter_selectTarget_noactive');
            }
        },

        onEnteringState_interceptDeclare: function(args) {
            if (this.isCurrentPlayerActive()) {
                this.updateActionPrompts('interceptDeclare', args);
                if (this._defenderPreview && parseInt(args && args.defender_id) === parseInt(this.player_id)) {
                    const idx = this._defenderPreview.selected_slot_index;
                    const type = this._defenderPreview.selected_slot_type;
                    const label = (this.cardTypeNames && this.cardTypeNames[type]) ? this.cardTypeNames[type] : (type || '?');
                    const span = $('hc_prompt_text');
                    if (span) {
                        span.innerHTML = _('Attacker selected Card ${n}, ${t}')
                            .replace('${n}', idx)
                            .replace('${t}', label);
                    }
                    try {
                        if (this._defenderPreview.zone === 'hand') {
                            const host = $('hc_current_hand');
                            if (host && host.children && host.children.length >= idx) {
                                dojo.addClass(host.children[idx - 1], 'hc_pulse_slot');
                            }
                        }
                    } catch(e) { console.warn('highlight failed', e); }
                }
                // Allow the defender to select a Laser Pointer card from hand
                if (this.playerHand && this.playerHand.setSelectionMode) {
                    this.playerHand.setSelectionMode(1);
                }
            }
        },

        onEnteringState_interceptChallengeWindow: function(args) {
            if (this.isCurrentPlayerActive()) {
                this.updateActionPrompts('interceptChallengeWindow', args);
            }
        },

        onEnteringState_selectPenalty: function(args) {
            if (this.isCurrentPlayerActive()) {
                this.showPenaltySelection(args);
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function( stateName )
        {
            console.log( 'Leaving state: '+stateName );
            
            switch( stateName )
            {
                case 'awaitDeclaration':
                    // Disable hand selection
                    this.playerHand.setSelectionMode(0);
                    this.hideDeclarationDialog();
                    break;
                case 'interceptDeclare':
                    try { dojo.query('.hc_pulse_slot').removeClass('hc_pulse_slot'); } catch(e) {}
                    break;
                case 'interceptDeclare':
                    // Disable hand selection after intercept window
                    if (this.playerHand && this.playerHand.setSelectionMode) {
                        this.playerHand.setSelectionMode(0);
                    }
                    break;
                    
                case 'selectTarget':
                    this.hideTargetSelection();
                    break;
                    
                case 'challengeWindow':
                case 'interceptChallengeWindow':
                    // Clear challenge UI
                    dojo.query('#challenge_btn, #pass_challenge_btn').forEach(dojo.destroy);
                    // Remove declared preview
                    var prev = $('hc_declared_preview'); if (prev) dojo.destroy(prev);
                    break;
                case 'attackerSelectTruthfulPenalty':
                case 'alleyCatEffectSelect':
                case 'challengerSelectBluffPenalty':
                    var ph = $('hc_penalty_hand'); if (ph) dojo.destroy(ph);
                    break;
            }               
        }, 

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //        
        onUpdateActionButtons: function( stateName, args )
        {
            console.log( 'onUpdateActionButtons: '+stateName, args );
                      
            if( this.isCurrentPlayerActive() )
            {            
                switch( stateName )
                {
                    case 'awaitDeclaration':
                        // No action buttons needed - use card selection
                        break;
                        
                case 'challengeWindow':
                        // Ensure buttons appear in MULTIPLE_ACTIVE state via action bar
                        if (args && args.eligible_challengers && args.eligible_challengers.includes(parseInt(this.player_id))) {
                            this.addActionButton('challenge_btn', _('Challenge'), 'onChallenge');
                            this.addActionButton('pass_challenge_btn', _('Pass'), 'onPassChallenge', null, false, 'gray');
                        }
                        break;
                        
                    case 'interceptDeclare':
                        this.addActionButton('intercept_btn', _('Intercept with Laser Pointer'), 'onDeclareIntercept');
                        this.addActionButton('pass_intercept_btn', _('Allow Attack'), 'onPassIntercept', null, false, 'gray');
                        break;
                        
                case 'interceptChallengeWindow':
                    this.addActionButton('challenge_intercept_btn', _('Challenge Laser Pointer'), 'onChallengeIntercept');
                    this.addActionButton('pass_intercept_challenge_btn', _('Pass'), 'onPassChallengeIntercept', null, false, 'gray');
                    break;

                case 'attackerSelectTruthfulPenalty':
                    if (args && args.challengers && args.challengers.length > 0) {
                        const challenger = args.challengers[0];
                        this._penaltyArgs = {
                            target_player_id: challenger.player_id
                        };
                        this.renderPenaltyHand(challenger.hand_count || 0, (i)=>this.onPickTruthPenalty(i));
                    }
                    break;
                case 'alleyCatEffectSelect':
                    if (args && args.challengers && args.challengers.length > 0) {
                        const target = args.challengers[0];
                        this._penaltyArgs = { target_player_id: target.player_id };
                        this.renderPenaltyHand(target.hand_count || 0, (i)=>this.onPickTruthPenalty(i));
                    }
                    break;
                case 'catnipEffectSelect':
                    if (args && args.challengers && args.challengers.length > 0) {
                        const target = args.challengers[0];
                        this._penaltyArgs = { target_player_id: target.player_id };
                        this.renderPenaltyHand(target.hand_count || 0, (i)=>this.onPickTruthPenalty(i));
                    }
                    break;
                case 'animalControlEffectSelect':
                    if (args && args.challengers && args.challengers.length > 0) {
                        const target = args.challengers[0];
                        this._penaltyArgs = { target_player_id: target.player_id };
                        this.renderPenaltyHand(target.hand_count || 1, (i)=>this.onPickTruthPenalty(i));
                    }
                    break;

                case 'challengerSelectBluffPenalty':
                    if (args) {
                        this._penaltyArgs = args;
                        this.renderPenaltyHand(args.hand_count || 0, (i)=>this.onPickBluffPenalty(i));
                    }
                    break;

                case 'selectTarget':
                    // Ensure the target UI appears even if onEnteringState timing is odd
                    if (args && args.valid_targets && args.valid_targets.length) {
                        const isActor = args && (parseInt(args.acting_player_id) === parseInt(this.player_id));
                        if (this.isCurrentPlayerActive() || isActor) {
                            this._log('buttons_selectTarget_force_show');
                            this.showTargetSelection(args);
                        }
                    }
                    if (args && args.canSkip) {
                        this.addActionButton('skip_targeting_btn', _('Skip Targeting'), 'onSkipTargeting', null, false, 'gray');
                    }
                    break;
                }
            }
        },        

        ///////////////////////////////////////////////////
        //// Utility methods
        
        updateActionPrompts: function(stateName, args) {
            const promptsDiv = $('hc_action_prompts');
            if (!promptsDiv) return;
            
            // Use a dedicated text span so we don't wipe the preview element
            let textSpan = $('hc_prompt_text');
            if (!textSpan) {
                textSpan = dojo.create('span', { id: 'hc_prompt_text' }, promptsDiv);
            }
            
            let promptText = '';
            
            switch(stateName) {
                case 'awaitDeclaration':
                    promptText = _('Select a card from your hand and declare its type');
                    break;
                case 'challengeWindow':
                    // Yellow area should focus on the declared info only; system shows waiting text above.
                    promptText = ''; // Let renderDeclaredPreview handle the display
                    break;
                case 'selectTarget':
                    promptText = _('Select your target.');
                    break;
                case 'interceptDeclare':
                    promptText = _('You are being targeted! Intercept with Laser Pointer?');
                    break;
                case 'interceptChallengeWindow':
                    promptText = _('Player claims to have Laser Pointer. Challenge?');
                    break;
                case 'attackerSelectTruthfulPenalty':
                    promptText = _('You may discard one card from opponent\'s hand');
                    break;
                case 'alleyCatEffectSelect':
                    promptText = _('You may discard one card from opponent\'s hand');
                    break;
                case 'catnipEffectSelect':
                    promptText = _('Select one card from opponent\'s hand to steal');
                    break;
                case 'animalControlEffectSelect':
                    promptText = _('Select one face-down herd card');
                    break;
                case 'challengerSelectBluffPenalty':
                    promptText = _('Select opponent card to discard');
                    break;
            }
            
            textSpan.innerHTML = promptText;

            // For challenge window, show a small declared preview to all players, using fallback when needed
            if (stateName === 'challengeWindow') {
                let dTypePrev = null;
                if (args) {
                    if (args.declared_type !== undefined && args.declared_type !== null) dTypePrev = args.declared_type;
                    else if (args.declared_card !== undefined && args.declared_card !== null) dTypePrev = args.declared_card;
                }
                if (dTypePrev === null || dTypePrev === undefined) dTypePrev = this.currentDeclaredType;
                if (dTypePrev !== null && dTypePrev !== undefined) this.renderDeclaredPreview(dTypePrev);
            }
        },

        renderDeclaredPreview: function(declaredType) {
            // Small preview under prompts: face-down card + declared type label
            const promptsDiv = $('hc_action_prompts');
            if (!promptsDiv) return;
            const prevId = 'hc_declared_preview';
            let prev = $(prevId);
            if (!prev) {
                prev = dojo.create('div', { id: prevId, style: 'margin-top:8px; display:flex; align-items:center; gap:8px;' }, promptsDiv);
                const card = dojo.create('div', { className: 'hc_card hc_face_down', style: 'width:36px;height:48px;border-width:1px;background-image:url('+ (typeof g_gamethemeurl!=='undefined'? g_gamethemeurl : '') + 'img/herding_cats_art/cardback.jpeg);background-size:cover;background-position:center;' }, prev);
                // background-image is set by CSS class hc_face_down
                const label = (this.cardTypeNames && this.cardTypeNames[declaredType]) ? this.cardTypeNames[declaredType] : declaredType;
                dojo.create('span', { innerHTML: dojo.string.substitute(_('Declared as: ${type}'), { type: label }) }, prev);
            } else {
                const label = (this.cardTypeNames && this.cardTypeNames[declaredType]) ? this.cardTypeNames[declaredType] : declaredType;
                prev.querySelector('span').innerHTML = dojo.string.substitute(_('Declared as: ${type}'), { type: label });
            }
        },

        showDeclarationDialog: function(cardId) {
            this.selectedCard = cardId;
            dojo.style('hc_declare_overlay', 'display', 'flex');
        },

        hideDeclarationDialog: function() {
            dojo.style('hc_declare_overlay', 'display', 'none');
            this.selectedCard = null;
        },

        showTargetSelection: function(args) {
            if (!args || !args.valid_targets) return;
            this._log('showTargets_'+(args.valid_targets ? args.valid_targets.length : 0));

            let overlay = $('hc_target_overlay');
            let optionsDiv = $('hc_target_options');

            // If overlay/template nodes are missing (safety), fallback to inline rendering
            if (!overlay || !optionsDiv) {
                this._log('fallback_inline');
                const promptsDiv = $('hc_action_prompts');
                if (!promptsDiv) return;
                let inline = $('hc_inline_targets');
                if (inline) dojo.destroy(inline);
                inline = dojo.create('div', { id: 'hc_inline_targets', style: 'margin-top:10px; display:flex; gap:8px; flex-wrap:wrap; justify-content:center;' }, promptsDiv);
                const zone = args.target_zone || '';
                args.valid_targets.forEach(t => {
                    const id = (t.id !== undefined) ? t.id : (t.player_id !== undefined ? t.player_id : t.card_id);
                    const name = (t.name) || (t.player_name) || (_('Target'));
                    const btn = dojo.create('button', { className: 'hc_target_btn', innerHTML: name }, inline);
                    dojo.attr(btn, 'data-target-id', id);
                    dojo.attr(btn, 'data-target-zone', (t.zone || zone || ''));
                    dojo.connect(btn, 'onclick', this, () => this.onSelectTarget(id, (t.zone || zone || '')));
                });
                return;
            }

            // Clear previous options
            optionsDiv.innerHTML = '';
            this._log('overlay_ok');

            const zone = args.target_zone || null;

            // Normalize and add target buttons (server may send different shapes)
            args.valid_targets.forEach(t => {
                const id = (t.id !== undefined) ? t.id : (t.player_id !== undefined ? t.player_id : t.card_id);
                const name = (t.name) || (t.player_name) || (_('Target'));
                const btn = dojo.place(dojo.string.substitute(jstpl_target_button, {
                    target_id: id,
                    target_zone: (t.zone || zone || ''),
                    target_name: name
                }), optionsDiv);
                dojo.connect(btn, 'onclick', this, () => {
                    this.onSelectTarget(id, (t.zone || zone || ''));
                });
            });

            dojo.style(overlay, 'display', 'flex');
            this.targetSelectionActive = true;
        },

        hideTargetSelection: function() {
            dojo.style('hc_target_overlay', 'display', 'none');
            this.targetSelectionActive = false;
        },

        showPenaltySelection: function(args) {
            // Show UI for blind card selection from opponent
            if (args && args.target_player_id) {
                this.highlightValidTargets([{
                    player_id: args.target_player_id,
                    zone: 'hand',
                    selectable: true
                }]);
            }
        },

        highlightValidTargets: function(targets) {
            // Remove previous highlights
            dojo.query('.hc_selectable').removeClass('hc_selectable');
            
            targets.forEach(target => {
                if (target.zone === 'hand') {
                    // Highlight player board for hand targeting
                    dojo.addClass('hc_player_board_' + target.player_id, 'hc_selectable');
                } else if (target.zone === 'herd') {
                    // Highlight face-down herd cards
                    const herdContainer = $('hc_herd_face_down_' + target.player_id);
                    if (herdContainer) {
                        dojo.query('.hc_card', herdContainer).addClass('hc_selectable');
                    }
                }
            });
        },

        updateHandCounts: function(handCounts) {
            for (let playerId in handCounts) {
                const element = $('hc_hand_count_' + playerId);
                if (element) {
                    element.innerHTML = handCounts[playerId];
                }
            }
        },

        animateCardMovement: function(cardElement, fromContainer, toContainer, callback) {
            // Simple animation - move card with CSS transition
            const cardClone = cardElement.cloneNode(true);
            
            // Position clone at original location
            const fromRect = fromContainer.getBoundingClientRect();
            const toRect = toContainer.getBoundingClientRect();
            
            cardClone.style.position = 'fixed';
            cardClone.style.left = fromRect.left + 'px';
            cardClone.style.top = fromRect.top + 'px';
            cardClone.style.zIndex = '1000';
            
            document.body.appendChild(cardClone);
            
            // Animate to destination
            setTimeout(() => {
                cardClone.style.transition = 'all 0.5s ease';
                cardClone.style.left = toRect.left + 'px';
                cardClone.style.top = toRect.top + 'px';
                
                setTimeout(() => {
                    document.body.removeChild(cardClone);
                    if (callback) callback();
                }, 500);
            }, 50);
        },

        ///////////////////////////////////////////////////
        //// Player's action
        
        onPlayerHandSelectionChanged: function() {
            const selection = this.playerHand.getSelectedItems();
            if (selection.length > 0) {
                const cardId = selection[0].id;
                // Show bluff/declaration dialog so player can choose any identity
                if (this.gamedatas.gamestate && this.gamedatas.gamestate.name === 'awaitDeclaration') {
                    this.showDeclarationDialog(cardId);
                }
            }
        },

        onCardTypeDeclared: function(evt) {
            const declaredType = parseInt(dojo.attr(evt.currentTarget, 'data-type'));
            
            // Fallback: if no cached selection, read current stock selection
            if (!this.selectedCard) {
                const sel = this.playerHand.getSelectedItems();
                if (sel && sel.length > 0) {
                    this.selectedCard = sel[0].id;
                }
            }
            
            if (!this.selectedCard && this.selectedCard !== 0) {
                this.showMessage(_('Please select a card from your hand first'), 'error');
                return;
            }
            
            // Ensure numeric
            const cardId = parseInt(this.selectedCard);
            
            this.hideDeclarationDialog();
            
            // Send declaration to server
            this.ajaxcall(this._actionUrl("actDeclare"), {
                card_id: cardId,
                declared_type: declaredType,
                lock: true
            }, this, function(result) {
                // Success handled by notification
            }, function(is_error) {
                // Error handling
                console.error('Declaration failed', is_error);
            });
        },

        onCancelDeclaration: function() {
            this.hideDeclarationDialog();
            // Deselect card
            this.playerHand.unselectAll();
        },

        onChallenge: function() {
            // Get the actor_id from game state args if available
            let actorId = null;
            if (this.gamedatas.gamestate && this.gamedatas.gamestate.args && this.gamedatas.gamestate.args.actor_id) {
                actorId = this.gamedatas.gamestate.args.actor_id;
            }
            
            const params = { lock: true };
            if (actorId) {
                params.actor_id = actorId;
            }
            
            this.ajaxcall(this._actionUrl("actChallenge"), params, this, function(result) {
                // Success handled by notification
            });
        },

        onPassChallenge: function() {
            this.ajaxcall(this._actionUrl("actPassChallenge"), {
                lock: true
            }, this, function(result) {
                // Success handled by notification
            });
        },

        onPickTruthPenalty: function(index) {
            const args = this._penaltyArgs || {};
            const targetId = args.target_player_id;
            this.ajaxcall(this._actionUrl("actSelectBlindFromChallenger"), {
                player_id: targetId,
                card_index: index,
                lock: true
            }, this, function(result) {});
        },

        onPickBluffPenalty: function(index) {
            this.ajaxcall(this._actionUrl("actSelectBlindFromActor"), {
                card_index: index,
                lock: true
            }, this, function(result) {});
        },

        renderPenaltyHand: function(count, onClick) {
            const promptsDiv = $('hc_action_prompts');
            if (!promptsDiv) return;
            const id = 'hc_penalty_hand';
            let host = $(id);
            if (host) dojo.destroy(host);
            host = dojo.create('div', { id, style: 'margin-top:8px; display:flex; gap:6px; flex-wrap:wrap;' }, promptsDiv);
            for (let i = 0; i < count; i++) {
                const c = dojo.create('div', { className: 'hc_card hc_face_down', style: 'width:48px;height:64px;border-width:1px;cursor:pointer;' }, host);
                // Use 1-based slot numbers for clarity and to match server indexing
                const slotNo = i + 1;
                dojo.connect(c, 'onclick', this, ()=> onClick(slotNo));
                c.title = _('Pick slot ') + slotNo;
            }
        },

        onSelectTarget: function(targetId, targetZone) {
            this.hideTargetSelection();
            
            // Send both player_id (preferred) and legacy slot_index for compatibility
            this.ajaxcall(this._actionUrl("actSelectTargetSlot"), {
                player_id: targetId,
                slot_index: targetId,
                zone: targetZone,
                lock: true
            }, this, function(result) {
                // Success handled by notification
            });
        },

        onSkipTargeting: function() {
            this.ajaxcall(this._actionUrl("actSkipTargeting"), {
                lock: true
            }, this, function(result) {
                // Success handled by notification
            });
        },

        onCancelTargeting: function() {
            this.hideTargetSelection();
        },

        onDeclareIntercept: function() {
            // Choose zone based on declared effect type: Animal Control intercepts from herd, others from hand
            const zone = (this.currentDeclaredType == this.CARD_TYPE_ANIMALCONTROL) ? 'herd' : 'hand';
            let cardId = 0;
            if (zone === 'hand') {
                let sel = [];
                try { sel = this.playerHand ? this.playerHand.getSelectedItems() : []; } catch(e) { sel = []; }
                if (!sel || sel.length === 0) {
                    this.showMessage(_('Please select Laser Pointer (or a card to bluff as Laser Pointer) from hand'), 'error');
                    return;
                }
                cardId = parseInt(sel[0].id);
                if (isNaN(cardId)) {
                    this.showMessage(_('Invalid selection'), 'error');
                    return;
                }
            } else {
                // Herd: server will validate minimally; use a placeholder id
                cardId = 600000 + Math.floor(Math.random()*1000);
            }

            this.ajaxcall(this._actionUrl("actDeclareIntercept"), {
                card_id: cardId,
                zone: zone,
                lock: true
            }, this, function(result) {
                // Success handled by notification
            }, function(is_error) {
                console.error('Intercept declaration failed', is_error);
            });
        },

        onPassIntercept: function() {
            this.ajaxcall(this._actionUrl("actPassIntercept"), {
                lock: true
            }, this, function(result) {
                // Success handled by notification
            });
        },

        onChallengeIntercept: function() {
            this.ajaxcall(this._actionUrl("actChallengeIntercept"), {
                lock: true
            }, this, function(result) {
                // Success handled by notification
            });
        },

        onPassChallengeIntercept: function() {
            this.ajaxcall(this._actionUrl("actPassChallengeIntercept"), {
                lock: true
            }, this, function(result) {
                // Success handled by notification
            });
        },

        onSelectBlindCard: function(playerId, zone) {
            // Handle blind card selection for penalties
            this.ajaxcall(this._actionUrl("actSelectBlindFromActor"), {
                target_player: playerId,
                lock: true
            }, this, function(result) {
                // Success handled by notification
            });
        },
        
        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your herdingcats.game.php file.
        
        */
        setupNotifications: function()
        {
            console.log( 'notifications subscriptions setup' );
            
            // automatically listen to the notifications, based on the `notif_xxx` function on this class.
            this.bgaSetupPromiseNotifications();

            // Set notification durations
            this.notifqueue.setSynchronousDuration(500);
        },  

        // Notification handlers

        notif_cardPlayed: async function( args )
        {
            console.log( 'notif_cardPlayed', args );
            
            const playerId = args.player_id;
            const cardId = args.card_id;
            const declaredType = args.declared_type;
            
            // Remove card from player's hand if it's current player
            if (playerId == this.player_id) {
                this.playerHand.removeFromStockById(cardId);
            }
            
            // Update hand counts
            this.updateHandCounts(args.hand_counts);
            
            // Update action prompts
            this.updateActionPrompts('challengeWindow', {
                declared_card: declaredType,
                acting_player_name: args.player_name
            });
        },

        notif_defenderTargetPreview: async function(args) {
            this._defenderPreview = {
                selected_slot_index: args.selected_slot_index,
                selected_slot_type: args.selected_slot_type,
                zone: args.zone
            };
        },

        notif_targetSlotSelected: async function(args) {
            this._lastSelectedSlot = { zone: args.zone, index: args.selected_slot_index, target: args.target_player_id };
        },

        notif_interceptApplied: async function(args) {
            const effect = args.effect_type;
            const label = (this.cardTypeNames && this.cardTypeNames[effect]) ? this.cardTypeNames[effect] : effect;
            this.showMessage(dojo.string.substitute(_('Intercept applied (${label})'), { label }), 'info');
        },

        notif_interceptChallengeResult: async function(args) {
            // Lightweight log message for intercept challenge outcome
            try {
                const bluff = !!args.was_bluffing;
                const who = args.defender_id;
                const zone = args.zone || 'hand';
                if (bluff) {
                    this.showMessage(_('Intercept bluff caught: defender loses presented card; attack proceeds.'), 'info');
                } else {
                    this.showMessage(_('Intercept stands: challenger discards a blind penalty.'), 'info');
                }
            } catch (e) {
                console.warn('notif_interceptChallengeResult error', e);
            }
        },

        notif_challenge: async function( args )
        {
            console.log( 'notif_challenge', args );
            
            // Show challenge message
            this.showMessage(dojo.string.substitute(_('${challenger} challenges ${player}!'), {
                challenger: args.challenger_name,
                player: args.challenged_name
            }), 'info');
        },

        notif_challengeResult: async function( args )
        {
            console.log( 'notif_challengeResult', args );

            // Accept both legacy and structured keys
            const wasBluffing = (args.was_bluffing !== undefined) ? args.was_bluffing : args.bluffing;
            const playerName = args.player_name || _('Player');

            // Determine declared and actual labels (prefer numeric types → mapped names; fallback to provided strings)
            const declaredType = (args.declared_type !== undefined && args.declared_type !== null)
                ? args.declared_type : args.declared_card;
            const actualType = (args.actual_card_type !== undefined && args.actual_card_type !== null)
                ? args.actual_card_type : args.actual_card;

            const typeLabel = (t) => {
                if (t === undefined || t === null) return _('unknown');
                // Numeric id
                if (typeof t === 'number' || /^[0-9]+$/.test(String(t))) {
                    const key = parseInt(t);
                    return (this.cardTypeNames && this.cardTypeNames[key]) ? this.cardTypeNames[key] : _('unknown');
                }
                // String label from server
                return t;
            };

            if (wasBluffing) {
                this.showMessage(dojo.string.substitute(_('${player} was bluffing! Card was ${actual} not ${declared}'), {
                    player: playerName,
                    actual: typeLabel(actualType),
                    declared: typeLabel(declaredType)
                }), 'info');
            } else {
                this.showMessage(dojo.string.substitute(_('${player} was truthful! Card was indeed ${declared}'), {
                    player: playerName,
                    declared: typeLabel(declaredType)
                }), 'info');
            }
        },

        notif_handCountUpdate: async function( args )
        {
            console.log( 'notif_handCountUpdate', args );
            this.updateHandCounts(args.hand_counts);
        },

        notif_herdUpdate: async function( args )
        {
            console.log( 'notif_herdUpdate', args );
            
            const playerId = args.player_id;
            const card = args.card;
            const isVisible = args.visible;
            
            if (isVisible) {
                // Add to face-up herd
                this.playerHerds[playerId].faceUp.addToStockWithId(card.type, card.id);
            } else {
                // Add to face-down herd
                this.playerHerds[playerId].faceDown.addToStockWithId(0, card.id);
            }
        },

        notif_discardUpdate: async function( args )
        {
            console.log( 'notif_discardUpdate', args );
            const playerId = args.player_id;
            if (!this.playerDiscards[playerId]) return;

            // If a full list is provided, replace; otherwise append a single card.
            if (args.discard_cards !== undefined) {
                const cardsSpec = args.discard_cards;
                const cards = Array.isArray(cardsSpec) ? cardsSpec : Object.values(cardsSpec || {});
                this.playerDiscards[playerId].removeAll();
                cards.forEach(card => {
                    if (card && card.id !== undefined && card.type !== undefined) {
                        this.playerDiscards[playerId].addToStockWithId(card.type, card.id);
                    }
                });
                return;
            }

            if (args.card && args.card.id !== undefined && args.card.type !== undefined) {
                this.playerDiscards[playerId].addToStockWithId(args.card.type, args.card.id);
            }
        },

        notif_alleyCatIneffective: async function( args )
        {
            console.log('notif_alleyCatIneffective', args);
            const target = args && (args.target_name || args.target_id);
            this.showMessage(dojo.string.substitute(_("Ineffective: ${target} reveals an Alley Cat (returns to hand)"), {
                target: target || _('target')
            }), 'info');
        },

        notif_cardRemoved: async function( args )
        {
            console.log( 'notif_cardRemoved', args );
            
            const playerId = args.player_id;
            const cardId = args.card_id;
            const fromZone = args.from_zone;
            
            // Remove card from appropriate location
            if (fromZone === 'herd_down') {
                this.playerHerds[playerId].faceDown.removeFromStockById(cardId);
            } else if (fromZone === 'herd_up') {
                this.playerHerds[playerId].faceUp.removeFromStockById(cardId);
            } else if (fromZone === 'hand' && playerId == this.player_id) {
                this.playerHand.removeFromStockById(cardId);
            }
        },

        notif_cardStolen: async function( args )
        {
            console.log( 'notif_cardStolen', args );
            
            const fromPlayerId = args.from_player_id;
            const toPlayerId = args.to_player_id;
            const card = args.card;
            
            // Remove from source (if current player's hand)
            if (fromPlayerId == this.player_id) {
                this.playerHand.removeFromStockById(card.id);
            }
            
            // Add to destination herd (face-down)
            this.playerHerds[toPlayerId].faceDown.addToStockWithId(0, card.id);
            
            // Update hand counts
            this.updateHandCounts(args.hand_counts);
        },

        notif_effectResolved: async function( args )
        {
            console.log( 'notif_effectResolved', args );
            
            // Display effect resolution message
            if (args.message) {
                this.showMessage(args.message, 'info');
            }
        },

        notif_gameEnd: async function( args )
        {
            console.log( 'notif_gameEnd', args );
            
            // Update final scores
            if (args.scores) {
                for (let playerId in args.scores) {
                    const scoreElement = $('hc_score_' + playerId);
                    if (scoreElement) {
                        scoreElement.innerHTML = args.scores[playerId];
                    }
                }
            }
            
            // Show game end message
            this.showMessage(_('Game Over! Final scores calculated.'), 'info');
        },

        notif_playerEliminated: async function( args )
        {
            console.log( 'notif_playerEliminated', args );
            
            // Show elimination message
            this.showMessage(dojo.string.substitute(_('${player} has been eliminated (no cards in hand)!'), {
                player: args.player_name
            }), 'info');
            
            // Update UI to show player as eliminated
            const playerBoard = $('hc_player_board_' + args.player_id);
            if (playerBoard) {
                dojo.addClass(playerBoard, 'hc_eliminated');
            }
        }
   });             
});
