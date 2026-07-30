import{S as x}from"./sweetalert2.esm.all-0Z_61IYw.js";import{g as P}from"./pdfmake-DuRweCJ2.js";function j(e,n="No se pudo completar la operación."){var a,o,u,i,p;const t=((o=(a=e==null?void 0:e.response)==null?void 0:a.data)==null?void 0:o.errors)||null;return(t?(u=t[Object.keys(t)[0]])==null?void 0:u[0]:null)||((p=(i=e==null?void 0:e.response)==null?void 0:i.data)==null?void 0:p.message)||(e==null?void 0:e.message)||n}function _(e,n="Operación realizada"){return x.fire({customClass:{popup:"centro-apuntes-alert"},title:n,text:e,icon:"success",timer:1800,showConfirmButton:!1})}function H(e,n="Error"){return x.fire({customClass:{popup:"centro-apuntes-alert"},title:n,text:e,icon:"error",confirmButtonText:"Entendido"})}function W({title:e,text:n,confirmButtonText:t="Confirmar",icon:a="question"}){return x.fire({customClass:{popup:"centro-apuntes-alert"},title:e,text:n,icon:a,showCancelButton:!0,confirmButtonText:t,cancelButtonText:"Cancelar",reverseButtons:!0})}function M(e="los cambios no guardados"){return W({title:"Cancelar acción",text:`Se descartarán ${e}.`,confirmButtonText:"Sí, cancelar"})}function v(e){return e?new Date(e).toLocaleDateString("es-CL",{year:"numeric",month:"2-digit",day:"2-digit"}):"-"}function I(e){return e?new Date(e).toLocaleString("es-CL",{year:"numeric",month:"2-digit",day:"2-digit",hour:"2-digit",minute:"2-digit"}):"-"}function U(e){return e?new Date(e.getTime?e.getTime():e).toISOString().slice(0,16):""}function z(e){return e?String(e).replaceAll("_"," ").replace(/\b\w/g,n=>n.toUpperCase()):"-"}function V(e){return{pendiente:"warning",recibida:"info",en_proceso:"primary",pausada:"secondary",lista_para_retiro:"success",entregada:"success",rechazada:"danger",anulada:"secondary",urgente:"danger",entrega_inmediata:"danger",activa:"success",inactiva:"secondary",en_mantencion:"warning",danada:"danger",disponible:"success",stock_bajo:"warning",agotado:"danger",proximo_a_vencer:"warning",vencido:"danger",dado_de_baja:"secondary",ingreso:"success",salida:"danger",ajuste:"warning",perdida:"danger",devolucion:"info",baja:"secondary",solicitada:"warning",aprobada:"info"}[e]||"light"}function q(e,n=!1,t="Todos"){const a=(e||[]).map(o=>typeof o=="string"?{value:o,label:z(o)}:{value:o.value??o.id,label:o.label??o.name??o.display_name??z(o.value??o.id)});return n?[{value:null,label:t}].concat(a):a}function G(e,n=[]){const t={...e};return n.forEach(a=>{const o=t[a];(o==null||typeof o=="string"&&o.trim()==="")&&(t[a]=null)}),t}function X({categories:e=[],colors:n=["#2f7cf6"],horizontal:t=!1}={}){return{chart:{toolbar:{show:!1},fontFamily:"inherit"},colors:n,dataLabels:{enabled:!1},stroke:{curve:"smooth",width:3},xaxis:{categories:e},plotOptions:{bar:{horizontal:t,borderRadius:6,columnWidth:"45%"}},grid:{borderColor:"rgba(148, 163, 184, .2)",strokeDashArray:4},legend:{position:"top"},noData:{text:"Sin datos para mostrar",align:"center",verticalAlign:"middle"},tooltip:{shared:!0,intersect:!1}}}function J(e,n="label"){return(e||[]).map(t=>(t==null?void 0:t[n])??"-")}function K(e,n="total"){return(e||[]).map(t=>Number((t==null?void 0:t[n])||0))}function h(e){return String(e??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&apos;")}function T(e,n=null){const t=n?` ss:StyleID="${n}"`:"";return typeof e=="number"&&Number.isFinite(e)?`<Cell${t||' ss:StyleID="Number"'}><Data ss:Type="Number">${e}</Data></Cell>`:typeof e=="boolean"?`<Cell${t}><Data ss:Type="Boolean">${e?1:0}</Data></Cell>`:`<Cell${t}><Data ss:Type="String">${h(e)}</Data></Cell>`}function L(e,n){const t=[e.headers||[],...e.rows||[]];return Array.from({length:n},(a,o)=>{var i;if((i=e.columnWidths)!=null&&i[o])return e.columnWidths[o];const u=t.reduce((p,l)=>{const r=String((l==null?void 0:l[o])??"").length;return Math.max(p,Math.min(r,40))},10);return Math.min(250,Math.max(72,u*7.2))})}function Q(e,n,t={}){const a=new Set,u=((n||[]).length?n:[{title:"Reporte",rows:[["Sin datos"]]}]).map((c,b)=>{var R,k;const S=`Hoja ${b+1}`,w=String(c.title||S).replace(/[\\/?*\[\]:]/g," ").trim().slice(0,31)||S;let m=w,C=2;for(;a.has(m);){const d=` (${C})`;m=`${w.slice(0,31-d.length)}${d}`,C+=1}a.add(m);const A=c.rows||[],g=Math.max(1,((R=c.headers)==null?void 0:R.length)||0,...A.map(d=>d.length)),s=L(c,g).map(d=>`<Column ss:AutoFitWidth="0" ss:Width="${d}"/>`).join(""),f=`<Row ss:Height="28"><Cell ss:StyleID="Title" ss:MergeAcross="${g-1}"><Data ss:Type="String">${h(c.title||t.title||"Reporte")}</Data></Cell></Row>`,y=c.subtitle||t.subtitle||"",D=y?`<Row ss:Height="24"><Cell ss:StyleID="Subtitle" ss:MergeAcross="${g-1}"><Data ss:Type="String">${h(y)}</Data></Cell></Row>`:"",F=(k=c.headers)!=null&&k.length?`<Row ss:Height="24">${c.headers.map(d=>T(d,"Header")).join("")}</Row>`:"",E=(A.length?A:[["Sin datos"]]).map(d=>`<Row>${d.map(B=>T(B)).join("")}</Row>`).join(""),$=1+(D?1:0)+(F?1:0);return`<Worksheet ss:Name="${h(m)}">
      <Table>${s}${f}${D}${F}${E}</Table>
      <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
        <FreezePanes/><FrozenNoSplit/><SplitHorizontal>${$}</SplitHorizontal>
        <TopRowBottomPane>${$}</TopRowBottomPane><ActivePane>2</ActivePane>
        <ProtectObjects>False</ProtectObjects><ProtectScenarios>False</ProtectScenarios>
      </WorksheetOptions>
    </Worksheet>`}).join(""),i=`<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
  <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
    <Title>${h(t.title||"Reporte Centro de Apuntes")}</Title>
    <Author>${h(t.author||"Centro de Apuntes")}</Author>
    <Created>${new Date().toISOString()}</Created>
  </DocumentProperties>
  <Styles>
    <Style ss:ID="Default" ss:Name="Normal">
      <Alignment ss:Vertical="Center"/><Font ss:FontName="Arial" ss:Size="10"/>
    </Style>
    <Style ss:ID="Title">
      <Alignment ss:Vertical="Center"/><Font ss:FontName="Arial" ss:Size="16" ss:Bold="1" ss:Color="#FFFFFF"/>
      <Interior ss:Color="#405189" ss:Pattern="Solid"/>
    </Style>
    <Style ss:ID="Subtitle">
      <Alignment ss:Vertical="Center" ss:WrapText="1"/><Font ss:FontName="Arial" ss:Size="9" ss:Color="#586174"/>
      <Interior ss:Color="#EEF2FF" ss:Pattern="Solid"/>
    </Style>
    <Style ss:ID="Header">
      <Alignment ss:Vertical="Center" ss:WrapText="1"/><Font ss:FontName="Arial" ss:Size="9" ss:Bold="1" ss:Color="#2A3042"/>
      <Interior ss:Color="#DDE5FF" ss:Pattern="Solid"/>
      <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AEBBDD"/></Borders>
    </Style>
    <Style ss:ID="Number"><Alignment ss:Horizontal="Right" ss:Vertical="Center"/><NumberFormat ss:Format="#,##0.00"/></Style>
  </Styles>
  ${u}
</Workbook>`,p=new Blob(["\uFEFF",i],{type:"application/vnd.ms-excel;charset=utf-8;"}),l=URL.createObjectURL(p),r=document.createElement("a");r.href=l,r.download=String(e).toLowerCase().endsWith(".xls")?e:`${e}.xls`,document.body.appendChild(r),r.click(),r.remove(),URL.revokeObjectURL(l)}function Y(e,n,t,a,o={}){const u=P(),i=[{text:n,style:"title"}],p=o.generatedAt||new Date().toLocaleString("es-CL");t&&i.push({text:t,style:"subtitle"}),(a||[]).forEach(l=>{var g;const r=l.headers||[],c=(g=l.rows)!=null&&g.length?l.rows:[["Sin datos"]],b=Math.max(1,r.length,...c.map(s=>s.length)),S=c.map(s=>Array.from({length:b},(f,y)=>y<s.length?s[y]:"")),w=[].concat(r.length?[r.map(s=>({text:String(s??""),style:"tableHeader"}))]:[]).concat(S.map(s=>s.map(f=>({text:String(f??"-"),style:"tableCell"})))),m={table:{headerRows:r.length?1:0,keepWithHeaderRows:1,dontBreakRows:!0,widths:l.widths||Array.from({length:b},()=>"*"),body:w},layout:{fillColor:s=>r.length&&s===0?"#DDE5FF":s%2===0?"#F8FAFD":null,hLineColor:()=>"#DCE2EC",vLineColor:()=>"#E8ECF2",hLineWidth:(s,f)=>s===0||s===f.table.body.length?.8:.35,vLineWidth:()=>.35,paddingLeft:()=>5,paddingRight:()=>5,paddingTop:()=>4,paddingBottom:()=>4},margin:[0,0,0,12]},C={text:l.title||"Sección",style:"section"};S.length<=10&&b<=7?i.push({stack:[C,m],unbreakable:!0}):i.push({...C,pageBreak:"before"},m)}),u.createPdf({pageSize:o.pageSize||"A4",pageOrientation:o.pageOrientation||"portrait",pageMargins:o.pageMargins||[32,44,32,38],header:()=>({text:o.headerText||"CENTRO DE APUNTES - REPORTE OPERATIVO",color:"#7A8498",fontSize:7,bold:!0,margin:[32,18,32,0]}),footer:(l,r)=>({columns:[{text:`Generado ${p}`,alignment:"left"},{text:`Página ${l} de ${r}`,alignment:"right"}],color:"#7A8498",fontSize:7,margin:[32,10,32,0]}),content:i,styles:{title:{fontSize:18,bold:!0,color:"#2A3042",margin:[0,0,0,4]},subtitle:{fontSize:9,color:"#667085",margin:[0,0,0,12]},section:{fontSize:11,bold:!0,color:"#405189",margin:[0,9,0,6]},tableHeader:{bold:!0,color:"#2A3042",fontSize:o.tableFontSize||8},tableCell:{color:"#3D4657",fontSize:o.tableFontSize||8}},defaultStyle:{fontSize:o.tableFontSize||8,lineHeight:1.15},info:{title:n,subject:t||"Reporte del Centro de Apuntes",author:o.author||"Centro de Apuntes"}}).download(String(e).toLowerCase().endsWith(".pdf")?e:`${e}.pdf`)}function Z(e,n){const t=window.open("","_blank","width=1100,height=800");t&&(t.document.write(`
    <html>
      <head>
        <title>${e}</title>
        <style>
          body { font-family: Arial, sans-serif; padding: 24px; color: #2a3042; }
          h1 { margin-bottom: 12px; }
          table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
          th, td { border: 1px solid #ced4da; padding: 8px; font-size: 12px; text-align: left; }
          th { background: #f8f9fa; }
        </style>
      </head>
      <body>
        <h1>${e}</h1>
        ${n}
      </body>
    </html>
  `),t.document.close(),t.focus(),t.print())}export{M as a,X as b,W as c,Q as d,Y as e,J as f,K as g,v as h,I as i,j,z as k,q as l,_ as m,G as n,V as o,Z as p,H as s,U as t};
