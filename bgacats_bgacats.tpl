{OVERALL_GAME_HEADER}

<div id="table-area">
  <div id="hand-area" aria-label="{HAND}"></div>

  <div id="players-area">
    <!-- BEGIN playerboard -->
    <div class="playerboard" id="playerboard_{PLAYER_ID}">
      <div class="pb-header">
        <span class="pb-name">{PLAYER_NAME}</span>
        <span class="pb-stats" id="pb_stats_{PLAYER_ID}"></span>
      </div>
      <div class="pb-sections">
        <div class="pb-herd" id="herd_{PLAYER_ID}" aria-label="{HERD}"></div>
        <div class="pb-herdup" id="herdup_{PLAYER_ID}" aria-label="{HERD_FACEUP}"></div>
        <div class="pb-discard" id="discard_{PLAYER_ID}" aria-label="{DISCARD}"></div>
      </div>
    </div>
    <!-- END playerboard -->
  </div>

  <div id="action-panel">
    <div id="decl-area"></div>
    <div id="challenge-area"></div>
    <div id="target-area"></div>
    <div id="intercept-area"></div>
  </div>
</div>

{OVERALL_GAME_FOOTER}