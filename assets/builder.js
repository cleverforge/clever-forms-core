jQuery(function($){
  const $json=$('#clever-fields-json'); if(!$json.length)return;
  let fields=[]; try{fields=JSON.parse($json.val()||'[]')}catch(e){}
  const cfg=window.CLEVER_FORMS_BUILDER||{}, types=cfg.fieldTypes||{};
  const canvas=$('#clever-canvas'), editor=$('#clever-field-editor'), preview=$('#clever-live-preview');
  const uid=()=>('f_'+Math.random().toString(36).slice(2,10));
  let editorIndex=-1, editorInitial='', editorDirty=false;

  function sync(){ $json.val(JSON.stringify(fields)); }
  function esc(v){return $('<div>').text(v??'').html()}
  function render(){
    canvas.empty();
    fields.forEach((f,i)=>{
      const item=$('<div class="clever-builder-field" data-i="'+i+'"><div class="clever-builder-field-main"><span class="dashicons dashicons-menu clever-drag-handle" aria-hidden="true"></span><div><strong></strong><div class="meta"></div></div></div><div class="clever-builder-field-actions"><button type="button" class="button edit">Edit</button> <button type="button" class="button duplicate">Duplicate</button> <button type="button" class="button delete">Delete</button></div></div>');
      item.find('strong').text(f.label||types[f.type]||f.type);
      item.find('.meta').text((types[f.type]||f.type)+' · '+(f.key||'')+' · '+(f.width||100)+'%');
      canvas.append(item);
    });
    sync(); renderPreview();
  }

  function previewInput(f){
    const label=esc(f.label||types[f.type]||'Field');
    const placeholder=esc(f.placeholder||'');
    const req=f.required?' <span class="clever-preview-required">*</span>':'';
    const description=f.description?`<small>${esc(f.description)}</small>`:'';
    const choices=(f.choices||[]).map(c=>esc(c));
    let control='';
    switch(f.type){
      case 'section': return `<div class="clever-preview-section"><h3>${label}</h3>${description}</div>`;
      case 'page': return `<div class="clever-preview-pagebreak"><span>Page Break</span><strong>${label}</strong></div>`;
      case 'html': return `<div class="clever-preview-html">${f.html||'<em>HTML content</em>'}</div>`;
      case 'textarea': control=`<textarea rows="4" placeholder="${placeholder}" disabled></textarea>`; break;
      case 'select': control=`<select disabled><option>${placeholder||'Select...'}</option>${choices.map(c=>`<option>${c}</option>`).join('')}</select>`; break;
      case 'multiselect': control=`<select multiple size="4" disabled>${choices.map(c=>`<option>${c}</option>`).join('')}</select>`; break;
      case 'radio': control=`<div class="clever-preview-choices">${choices.map(c=>`<label><input type="radio" disabled> ${c}</label>`).join('')}</div>`; break;
      case 'checkbox': control=`<div class="clever-preview-choices">${choices.map(c=>`<label><input type="checkbox" disabled> ${c}</label>`).join('')}</div>`; break;
      case 'consent': control=`<label class="clever-preview-choice"><input type="checkbox" disabled> ${esc(f.description||'I agree.')}</label>`; break;
      case 'terms': control=`<div class="clever-preview-terms">${f.html||'<p>Terms of service</p>'}</div><label class="clever-preview-choice"><input type="checkbox" disabled> I accept the terms.</label>`; break;
      case 'file': control='<input type="file" disabled>'; break;
      case 'signature': control='<div class="clever-preview-signature"><span>Sign here</span></div><button type="button" class="button" disabled>Clear signature</button>'; break;
      case 'name': control='<div class="clever-preview-compound"><input type="text" placeholder="First" disabled><input type="text" placeholder="Last" disabled></div>'; break;
      case 'address': control='<div class="clever-preview-compound clever-preview-address"><input type="text" placeholder="Street" disabled><input type="text" placeholder="City" disabled><input type="text" placeholder="State / Region" disabled><input type="text" placeholder="Postal Code" disabled></div>'; break;
      case 'slider': control=`<input type="range" min="${esc(f.min_value||0)}" max="${esc(f.max_value||100)}" step="${esc(f.step||1)}" disabled>`; break;
      case 'list': control='<div class="clever-preview-list"><input type="text" placeholder="Item" disabled><button type="button" class="button" disabled>Add Row</button></div>'; break;
      case 'booking': control='<div class="clever-preview-compound"><input type="date" disabled><input type="time" disabled></div>'; break;
      case 'product': control=`<label class="clever-preview-choice"><input type="checkbox" disabled> ${label} — ${Number(f.price||0).toFixed(2)}</label>`; break;
      case 'subtotal': case 'discount': case 'tax': control=`<div class="clever-preview-money">${label}: <strong>0.00</strong></div>`; break;
      case 'hidden': return '<div class="clever-preview-hidden">Hidden field: '+label+'</div>';
      case 'unique_id': control='<input type="text" value="Generated automatically" disabled>'; break;
      default: control=`<input type="${['email','number','date','time','url'].includes(f.type)?f.type:'text'}" placeholder="${placeholder}" disabled>`;
    }
    return `<div class="clever-preview-field clever-preview-width-${esc(f.width||100)}"><label>${label}${req}</label>${control}${description}</div>`;
  }
  function renderPreview(){
    if(!preview.length)return;
    if(!fields.length){preview.html('<div class="clever-preview-empty">Add fields to see a live form preview.</div>');return;}
    preview.html(`<div class="clever-preview-form-title">${esc($('#title').val()||'Untitled Form')}</div><div class="clever-preview-fields">${fields.map(previewInput).join('')}</div><div class="clever-preview-submit"><button type="button" class="button button-primary" disabled>${esc($('input[name="clever_settings[button_text]"]').val()||'Submit')}</button></div>`);
  }
  $('#title, input[name="clever_settings[button_text]"]').on('input change',renderPreview);
  $('.clever-preview-device').on('click',function(){ $('.clever-preview-device').removeClass('is-active'); $(this).addClass('is-active'); preview.attr('data-device',$(this).data('device')); });

  function blank(type){return {id:uid(),type,key:type+'_'+(fields.length+1),label:types[type]||'Field',description:'',placeholder:'',required:0,width:'100',choices:[],default:'',html:'',condition_field:'',condition_operator:'equals',condition_value:'',accept:'pdf,doc,docx,jpg,jpeg,png',read_only:0,min_words:0,max_words:0,max_selections:0,min_date:'',max_date:'',copy_from:'',unique_mode:'alphanumeric',min_value:'0',max_value:'100',step:'1',price:'0',rename_template:''};}
  $('.clever-add-field').on('click',function(){fields.push(blank($(this).data('type')));render();openEditor(fields.length-1)});

  // Drag fields from the palette into the form canvas. Clicking remains available
  // for touch devices and users who prefer not to drag.
  $('.clever-add-field').attr('draggable','true')
    .on('dragstart',function(e){
      const transfer=e.originalEvent&&e.originalEvent.dataTransfer;
      if(!transfer)return;
      transfer.effectAllowed='copy';
      transfer.setData('text/clever-form-field',String($(this).data('type')||''));
      $(this).addClass('is-dragging');
    })
    .on('dragend',function(){$(this).removeClass('is-dragging');canvas.removeClass('is-drop-target');});

  canvas.on('dragover',function(e){
    const transfer=e.originalEvent&&e.originalEvent.dataTransfer;
    if(!transfer)return;
    const types=Array.from(transfer.types||[]);
    if(!types.includes('text/clever-form-field'))return;
    e.preventDefault();
    transfer.dropEffect='copy';
    canvas.addClass('is-drop-target');
  }).on('dragleave',function(e){
    if(!canvas[0].contains(e.relatedTarget))canvas.removeClass('is-drop-target');
  }).on('drop',function(e){
    const transfer=e.originalEvent&&e.originalEvent.dataTransfer;
    if(!transfer)return;
    const type=transfer.getData('text/clever-form-field');
    if(!type||!types[type])return;
    e.preventDefault();
    let insertAt=fields.length;
    const clientY=e.originalEvent.clientY;
    canvas.children('.clever-builder-field').each(function(i){
      if(insertAt!==fields.length)return;
      const rect=this.getBoundingClientRect();
      if(clientY<rect.top+(rect.height/2))insertAt=i;
    });
    fields.splice(insertAt,0,blank(type));
    canvas.removeClass('is-drop-target');
    render();
    openEditor(insertAt);
  });

  canvas.sortable({handle:'.clever-drag-handle',update:function(){const ordered=[];canvas.children().each(function(){ordered.push(fields[+$(this).data('i')])});fields=ordered;render();}});
  canvas.on('click','.delete',function(){if(confirm('Delete this field?')){fields.splice(+$(this).closest('.clever-builder-field').data('i'),1);render();}});
  canvas.on('click','.duplicate',function(){const i=+$(this).closest('.clever-builder-field').data('i');const copy=JSON.parse(JSON.stringify(fields[i]));copy.id=uid();copy.key=(copy.key||copy.type)+'_copy_'+(fields.length+1);copy.label=(copy.label||'Field')+' Copy';fields.splice(i+1,0,copy);render();});
  canvas.on('click','.edit',function(){openEditor(+$(this).closest('.clever-builder-field').data('i'));});

  function input(label,id,value,type='text'){return `<p><label>${label}<input id="${id}" type="${type}" value="${esc(value)}"></label></p>`}
  function editorSnapshot(){
    const o={}; editor.find('input,textarea,select').each(function(){const $el=$(this); if(!$el.attr('id'))return; o[$el.attr('id')]=$el.is(':checkbox')?$el.is(':checked'):$el.val();}); return JSON.stringify(o);
  }
  function requestClose(){
    if(editor.prop('hidden'))return;
    editorDirty=editorSnapshot()!==editorInitial;
    if(editorDirty && !window.confirm('You have unsaved field changes. Close without saving? Your changes will be lost.'))return;
    closeEditor();
  }
  function closeEditor(){editor.prop('hidden',true).removeClass('is-open'); $('body').removeClass('clever-modal-open'); editorIndex=-1; editorInitial=''; editorDirty=false;}

  function openEditor(i){const f=fields[i], choiceTypes=['select','radio','checkbox','multiselect']; editorIndex=i;
    let extras='';
    if(['text','email','phone','number','date','time','url','textarea','name','address'].includes(f.type)) extras+=`<p><label><input type="checkbox" id="a4-readonly" ${f.read_only?'checked':''}> Read only</label></p>`;
    if(f.type==='textarea') extras+=input('Minimum words','a4-min-words',f.min_words,'number')+input('Maximum words','a4-max-words',f.max_words,'number');
    if(f.type==='checkbox') extras+=input('Maximum selections','a4-max-selections',f.max_selections,'number');
    if(f.type==='date') extras+=input('Earliest date','a4-min-date',f.min_date,'date')+input('Latest date','a4-max-date',f.max_date,'date');
    if(!['section','page','html'].includes(f.type)) extras+=input('Copy value from field key','a4-copy-from',f.copy_from);
    if(f.type==='file') extras+=input('Rename template','a4-rename-template',f.rename_template);
    if(f.type==='unique_id') extras+=`<p><label>ID format<select id="a4-unique-mode"><option value="alphanumeric">Alphanumeric</option><option value="numeric">Numeric</option><option value="sequential">Sequential</option></select></label></p>`;
    if(f.type==='slider') extras+=input('Minimum','a4-min-value',f.min_value,'number')+input('Maximum','a4-max-value',f.max_value,'number')+input('Step','a4-step',f.step,'number');
    if(f.type==='product') extras+=input('Price','a4-price',f.price,'number');
    editor.prop('hidden',false).addClass('is-open').html(`
      <div class="clever-field-editor-backdrop"></div>
      <div class="clever-field-editor-dialog" role="dialog" aria-modal="true" aria-labelledby="clever-editor-title">
        <div class="clever-field-editor-header"><div><span class="clever-editor-kicker">Edit Field</span><h2 id="clever-editor-title">${esc(f.label||types[f.type]||'Field')}</h2><span class="clever-editor-type">${esc(types[f.type]||f.type)}</span></div><button type="button" class="clever-editor-x" aria-label="Close field editor">×</button></div>
        <div class="clever-field-editor-body">
          <p><label>Label<input id="a4-label" value="${esc(f.label)}"></label></p><p><label>Field key<input id="a4-key" value="${esc(f.key)}"></label></p>
          <p><label>Description<textarea id="a4-desc">${esc(f.description)}</textarea></label></p><p><label>Placeholder<input id="a4-placeholder" value="${esc(f.placeholder)}"></label></p>
          <p><label><input type="checkbox" id="a4-required" ${f.required?'checked':''}> Required</label></p><p><label>Width<select id="a4-width"><option>100</option><option>50</option><option>33</option><option>66</option></select></label></p>
          ${choiceTypes.includes(f.type)?`<p><label>Choices (one per line)<textarea id="a4-choices">${esc((f.choices||[]).join('\n'))}</textarea></label></p>`:''}
          ${['html','terms'].includes(f.type)?`<p><label>Content<textarea id="a4-html" rows="6">${esc(f.html)}</textarea></label></p>`:''}
          ${f.type==='file'?`<p><label>Allowed extensions<input id="a4-accept" value="${esc(f.accept)}"></label></p>`:''}
          ${extras}
          <h3>Conditional Logic</h3><p><label>Show when field key<input id="a4-cond-field" value="${esc(f.condition_field)}"></label></p><p><label>Operator<select id="a4-cond-op"><option value="equals">equals</option><option value="not_equals">does not equal</option><option value="contains">contains</option><option value="not_empty">is not empty</option></select></label></p><p><label>Value<input id="a4-cond-value" value="${esc(f.condition_value)}"></label></p>
        </div>
        <div class="clever-field-editor-footer"><button type="button" class="button" id="a4-close">Cancel</button><button type="button" class="button button-primary" id="a4-save">Save Field</button></div>
      </div>`);
    $('body').addClass('clever-modal-open');
    $('#a4-width').val(f.width||'100'); $('#a4-cond-op').val(f.condition_operator||'equals'); $('#a4-unique-mode').val(f.unique_mode||'alphanumeric');
    editorInitial=editorSnapshot(); editorDirty=false;
    editor.on('input.cleverEditor change.cleverEditor','input,textarea,select',function(){editorDirty=editorSnapshot()!==editorInitial;});
    editor.on('click.cleverEditor','.clever-editor-x,#a4-close,.clever-field-editor-backdrop',requestClose);
    $('#a4-save').on('click',()=>{
      f.label=$('#a4-label').val(); f.key=($('#a4-key').val()+'').toLowerCase().replace(/[^a-z0-9_]/g,'_'); f.description=$('#a4-desc').val(); f.placeholder=$('#a4-placeholder').val(); f.required=$('#a4-required').is(':checked')?1:0; f.width=$('#a4-width').val();
      if($('#a4-choices').length)f.choices=$('#a4-choices').val().split(/\r?\n/).map(x=>x.trim()).filter(Boolean); if($('#a4-html').length)f.html=$('#a4-html').val(); if($('#a4-accept').length)f.accept=$('#a4-accept').val();
      f.condition_field=$('#a4-cond-field').val(); f.condition_operator=$('#a4-cond-op').val(); f.condition_value=$('#a4-cond-value').val();
      if($('#a4-readonly').length)f.read_only=$('#a4-readonly').is(':checked')?1:0; if($('#a4-min-words').length)f.min_words=+$('#a4-min-words').val()||0; if($('#a4-max-words').length)f.max_words=+$('#a4-max-words').val()||0; if($('#a4-max-selections').length)f.max_selections=+$('#a4-max-selections').val()||0;
      if($('#a4-min-date').length)f.min_date=$('#a4-min-date').val(); if($('#a4-max-date').length)f.max_date=$('#a4-max-date').val(); if($('#a4-copy-from').length)f.copy_from=$('#a4-copy-from').val(); if($('#a4-rename-template').length)f.rename_template=$('#a4-rename-template').val();
      if($('#a4-unique-mode').length)f.unique_mode=$('#a4-unique-mode').val(); if($('#a4-min-value').length)f.min_value=$('#a4-min-value').val(); if($('#a4-max-value').length)f.max_value=$('#a4-max-value').val(); if($('#a4-step').length)f.step=$('#a4-step').val(); if($('#a4-price').length)f.price=$('#a4-price').val();
      closeEditor(); render();
    });
  }
  $(document).on('keydown.cleverEditor',function(e){if(e.key==='Escape'&&!editor.prop('hidden'))requestClose();});
  render();
});
