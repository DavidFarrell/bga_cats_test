define(["dojo","dojo/_base/declare","ebg/core/gamegui","ebg/stock"], function (dojo, declare) {
  return declare("bgagame.bgacats", ebg.core.gamegui, {

    constructor: function () {
      this.gamedatas = null;

      // Card constants (client side mirror)
      this.C = { KITTEN:1, SHOWCAT:2, ALLEYCAT:3, CATNIP:4, ANIMALCONTROL:5, LASERPOINTER:6 };
    },

    // -----------------------------------------------------------------------
    // Setup
    // -----------------------------------------------------------------------
    setup: function (gamedatas) {
      this.gamedatas = gamedatas;
      // The HTML template already draws the zones; we only drive prompts and clicks.
      // Nothing else is required here for target selection.
    },

    // -----------------------------------------------------------------------
    // State hooks
    // -----------------------------------------------------------------------
    onEnteringState: function (stateName, args) {
      const a = (args && args.args) ? args.args : (args || {}); // <-- normalise BGA args

      switch (stateName) {
        case "challengeWindow":
          this._updateActionPrompts("challengeWindow", a);
          break;

        case "selectTarget": {
          // Always refresh the prompt panel so spectators also see who/what is being picked
          this._updateActionPrompts("selectTarget", a);

          // Only the active player gets clickable buttons
          if (this.isCurrentPlayerActive()) {
            this._showTargetSelection(a); // <-- a contains valid_targets
          }
          break;
        }

        case "interceptDeclare":
          this._updateActionPrompts("interceptDeclare", a);
          break;

        case "interceptChallengeWindow":
          this._updateActionPrompts("interceptChallengeWindow", a);
          break;
      }
    },

    onLeavingState: function (stateName) {
      if (stateName === "selectTarget") {
        this._clearTargetSelection();
      }
    },

    // We keep the top-bar buttons lean. The yellow box shows the important choices.
    onUpdateActionButtons: function (stateName, args) {
      const a = (args && args.args) ? args.args : (args || {});
      if (!this.isCurrentPlayerActive()) return;

      if (stateName === "selectTarget" && a && a.canSkip) {
        this.addActionButton("hc_btn_skip_target", _("Pass"), () => this._onSkipTargeting(), null, false, "gray");
      }
    },

    // -----------------------------------------------------------------------
    // Prompts
    // -----------------------------------------------------------------------
    _updateActionPrompts: function (phase, data) {
      const wrap = dojo.byId("hc_action_prompts");
      if (!wrap) return;
      wrap.innerHTML = "";

      // If the server included the declared type, show a small header so everyone
      // sees what is currently being resolved.
      if (data && data.declared_card) {
        const d = dojo.create("div", { className: "hc_declared_preview" }, wrap);
        dojo.create("div", { innerHTML: _("Declared as: ") + this._typeName(data.declared_card), className: "hc_declared_title" }, d);
      }

      if (phase === "selectTarget") {
        dojo.create("h3", { innerHTML: _("Choose player to target") }, wrap);
      } else if (phase === "challengeWindow") {
        dojo.create("div", { innerHTML: _("Waiting for possible challenges...") }, wrap);
      } else if (phase === "interceptDeclare") {
        dojo.create("div", { innerHTML: _("The defender may declare a Laser Pointer.") }, wrap);
      } else if (phase === "interceptChallengeWindow") {
        dojo.create("div", { innerHTML: _("Players may challenge the intercept.") }, wrap);
      }
    },

    // -----------------------------------------------------------------------
    // Target selection UI - built directly in the yellow prompt area
    // -----------------------------------------------------------------------
    _showTargetSelection: function (args) {
      const host = dojo.byId("hc_action_prompts");
      if (!host) return;

      // Container so we can clear it on leaving state
      const boxId = "hc_target_inline";
      let box = dojo.byId(boxId);
      if (box) box.parentNode.removeChild(box);
      box = dojo.create("div", { id: boxId, className: "hc_target_inline" }, host);

      const targets = (args && args.valid_targets) ? args.valid_targets : [];
      if (!targets.length) {
        dojo.create("div", { className: "hc_no_targets", innerHTML: _("No valid targets. If the card does not require a target, press Pass.") }, box);
      } else {
        const row = dojo.create("div", { className: "hc_target_row" }, box);
        targets.forEach(t => {
          const b = dojo.create("a", {
            href: "#",
            className: "bgabutton bgabutton_blue hc_target_choice",
            innerHTML: t.name
          }, row);
          dojo.connect(b, "onclick", this, function (e) {
            dojo.stopEvent(e);
            this._onSelectTarget(parseInt(t.id), t.zone);
          });
        });
      }

      if (args && args.canSkip) {
        const pass = dojo.create("a", { href: "#", className: "bgabutton bgabutton_gray", innerHTML: _("Pass") }, box);
        dojo.connect(pass, "onclick", this, function (e) {
          dojo.stopEvent(e);
          this._onSkipTargeting();
        });
      }
    },

    _clearTargetSelection: function () {
      const el = dojo.byId("hc_target_inline");
      if (el && el.parentNode) el.parentNode.removeChild(el);
    },

    // -----------------------------------------------------------------------
    // Ajax actions
    // -----------------------------------------------------------------------
    _onSelectTarget: function (targetPlayerId, zone) {
      // Server handler expects the chosen player id in slot_index (historic naming) and the zone.
      this.ajaxcall(
        "/bgacats/bgacats/actSelectTargetSlot.html",
        { lock: true, slot_index: targetPlayerId, zone: zone },
        this,
        () => {},
        () => {}
      );
    },

    _onSkipTargeting: function () {
      this.ajaxcall(
        "/bgacats/bgacats/actSkipTargeting.html",
        { lock: true },
        this,
        () => {},
        () => {}
      );
    },

    // -----------------------------------------------------------------------
    // Small helpers
    // -----------------------------------------------------------------------
    _typeName: function (t) {
      switch (parseInt(t, 10)) {
        case this.C.KITTEN:        return _("Kitten");
        case this.C.SHOWCAT:       return _("Show Cat");
        case this.C.ALLEYCAT:      return _("Alley Cat");
        case this.C.CATNIP:        return _("Catnip");
        case this.C.ANIMALCONTROL: return _("Animal Control");
        case this.C.LASERPOINTER:  return _("Laser Pointer");
      }
      return "";
    },
  });
});