YUI.add("moodle-qbank_editquestion-chooser",function(r,e){var u="div.createnewquestion",a="div.chooserdialoguebody",c="div.choosertitle";function o(){o.superclass.constructor.apply(this,arguments)}r.extend(o,M.core.chooserdialogue,{initializer:function(){r.all("form").each(function(e){/question\/bank\/editquestion\/addquestion\.php/.test(e.getAttribute("action"))&&e.on("submit",this.displayQuestionChooser,this)},this)},displayQuestionChooser:function(e){var o,i,t,n=r.one(u+" "+a),s=r.one(u+" "+c);null===this.container&&(this.setup_chooser_dialogue(n,s,{}),this.prepare_chooser()),o=e.target.ancestor("form",!0),i=this.container.one("form"),t=o.all('input[type="hidden"]'),i.all("input.customfield").remove(),t.each(function(e){i.appendChild(e.cloneNode()).removeAttribute("id").addClass("customfield")}),this.display_chooser(e)}},{NAME:"questionChooser"}),M.question=M.question||{},M.question.init_chooser=function(e){return new o(e)}},"@VERSION@",{requires:["moodle-core-chooserdialogue"]});