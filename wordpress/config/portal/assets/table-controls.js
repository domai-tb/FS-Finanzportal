(function(){
function text(row){return (row.textContent||"").toLowerCase();}
function csvCell(value){value=(value||"").replace(/\s+/g," ").trim();return /[",\n;]/.test(value)?'"'+value.replace(/"/g,'""')+'"':value;}
function downloadCsv(root, rows){
  var table=root.querySelector("table");if(!table){return;}
  var headers=Array.prototype.slice.call(table.querySelectorAll("thead th")).map(function(th){return csvCell(th.textContent);});
  var lines=[headers.join(";")];
  rows.forEach(function(row){lines.push(Array.prototype.slice.call(row.children).map(function(td){return csvCell(td.textContent);}).join(";"));});
  var blob=new Blob(["\ufeff"+lines.join("\n")],{type:"text/csv;charset=utf-8"});
  var link=document.createElement("a");
  link.href=URL.createObjectURL(blob);
  link.download=(root.getAttribute("data-export-name")||"fs-finanzportal-export")+".csv";
  document.body.appendChild(link);link.click();document.body.removeChild(link);
  setTimeout(function(){URL.revokeObjectURL(link.href);},1000);
}
function enhance(root, mode){
  if(!root||root.dataset.fsfpTableControls==="1"){return;}
  root.dataset.fsfpTableControls="1";
  var prefix=mode==="unified"?"unified":"scoped";
  var tbody=root.querySelector("[data-"+prefix+"-body]");
  if(!tbody){return;}
  var search=root.querySelector("[data-"+prefix+"-search]");
  var status=root.querySelector("[data-"+prefix+"-status]");
  var fachschaft=root.querySelector("[data-"+prefix+"-fachschaft]");
  var prev=root.querySelector("[data-"+prefix+"-prev]");
  var next=root.querySelector("[data-"+prefix+"-next]");
  var pageLabel=root.querySelector("[data-"+prefix+"-page]");
  var empty=root.querySelector("[data-"+prefix+"-empty]");
  var exportButton=root.querySelector("[data-"+prefix+"-export]");
  var pageSize=10,page=1;
  var rows=Array.prototype.slice.call(tbody.querySelectorAll("tr")).map(function(row){return row.cloneNode(true);});
  var params=new URLSearchParams(window.location.search);
  var initialStatus=params.get("status")||"";
  if(status&&initialStatus){status.value=initialStatus;}
  function filtered(){
    var q=(search&&search.value||"").toLowerCase();
    var s=status&&status.value||"";
    var f=fachschaft&&fachschaft.value||"";
    return rows.filter(function(row){
      return (!q||text(row).indexOf(q)!==-1)&&(!s||row.getAttribute("data-status")===s)&&(!f||row.getAttribute("data-fachschaft")===f);
    });
  }
  function render(){
    var items=filtered(),pages=Math.max(1,Math.ceil(items.length/pageSize));
    if(page>pages){page=pages;}
    tbody.innerHTML="";
    items.slice((page-1)*pageSize,page*pageSize).forEach(function(row){tbody.appendChild(row.cloneNode(true));});
    if(empty){empty.hidden=items.length!==0;}
    if(pageLabel){pageLabel.textContent=items.length?("Seite "+page+" von "+pages+" · "+items.length+" Einträge"):"Keine Einträge";}
    if(prev){prev.disabled=page<=1;}
    if(next){next.disabled=page>=pages;}
    if(exportButton){exportButton.disabled=items.length===0;}
  }
  function reset(){page=1;render();}
  [search,status,fachschaft].forEach(function(control){if(control){control.addEventListener("input",reset);control.addEventListener("change",reset);}});
  if(prev){prev.addEventListener("click",function(){if(page>1){page--;render();}});}
  if(next){next.addEventListener("click",function(){page++;render();});}
  if(exportButton){exportButton.addEventListener("click",function(){downloadCsv(root,Array.prototype.slice.call(tbody.querySelectorAll("tr")));});}
  render();
}
document.querySelectorAll("[data-fsfp-table='scoped']").forEach(function(root){enhance(root,"scoped");});
document.querySelectorAll("[data-fsfp-table='unified']").forEach(function(root){enhance(root,"unified");});
})();
