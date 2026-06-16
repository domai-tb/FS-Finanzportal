(function(){
var fallback="{{default_url_js}}";
function pathParts(path){return (path||"").split("/").filter(Boolean);}
function isListPath(path){var parts=pathParts(path);if(parts.length===2&&parts[0]==="dashboard"&&(parts[1]==="beschluesse"||parts[1]==="zahlungsanweisungen")){return true;}return parts.length===3&&parts[0]==="dashboard"&&(parts[2]==="beschluesse"||parts[2]==="zahlungsanweisungen");}
function safeUrl(value){if(!value){return "";}try{var url=new URL(value,window.location.origin);if(url.origin!==window.location.origin||!isListPath(url.pathname)){return "";}return url.pathname+url.search+url.hash;}catch(e){return "";}}
var params=new URLSearchParams(window.location.search);
var target=safeUrl(params.get("return_to"))||fallback;
var absolute=new URL(target,window.location.origin).href;
function applyRedirect(){document.querySelectorAll(".fsfp-edit-page form.pods-form").forEach(function(form){form.dataset.location=absolute;form.setAttribute("data-location",absolute);var location=form.querySelector("[name=\"_pods_location\"]");if(location){location.value=absolute;}});}
applyRedirect();[250,750,1500].forEach(function(delay){setTimeout(applyRedirect,delay);});
})();
