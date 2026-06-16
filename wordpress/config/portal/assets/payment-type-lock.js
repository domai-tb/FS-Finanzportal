(function(){
var root=document.currentScript.closest(".fsfp-action-panel");if(!root){return;}
function field(name){return root.querySelector(`[name="${name}"],[name="pods_field_${name}"],[name$="[${name}]"],[id$="-${name}"],[id$="-pods-field-${name.replace(/_/g,"-")}"],[id$="_${name}"]`);}
function lock(){var status=field("zahlungs_status");var shouldLock={{should_lock_js}};["zahlungstyp","vorkasse_method","vorkasse_begruendung","empfaenger_details"].forEach(function(name){var el=field(name);if(el){el.readOnly=shouldLock;el.disabled=shouldLock;}});}
lock();[250,750,1500].forEach(function(delay){setTimeout(lock,delay);});
})();
