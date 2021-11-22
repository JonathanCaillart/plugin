this.wc=this.wc||{},this.wc.customerEffortScore=function(e){var t={};function o(c){if(t[c])return t[c].exports;var r=t[c]={i:c,l:!1,exports:{}};return e[c].call(r.exports,r,r.exports,o),r.l=!0,r.exports}return o.m=e,o.c=t,o.d=function(e,t,c){o.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:c})},o.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},o.t=function(e,t){if(1&t&&(e=o(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var c=Object.create(null);if(o.r(c),Object.defineProperty(c,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var r in e)o.d(c,r,function(t){return e[t]}.bind(null,r));return c},o.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return o.d(t,"a",t),t},o.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},o.p="",o(o.s=466)}({0:function(e,t){e.exports=window.wp.element},1:function(e,t,o){e.exports=o(47)()},14:function(e,t){e.exports=window.wp.compose},2:function(e,t){e.exports=window.wp.i18n},20:function(e,t){e.exports=window.wc.experimental},4:function(e,t){e.exports=window.wp.components},466:function(e,t,o){"use strict";o.r(t),o.d(t,"CustomerEffortScore",(function(){return d}));var c=o(0),r=o(1),n=o.n(r),a=o(2),i=o(14),l=o(7),s=o(4),u=o(20);function m({recordScoreCallback:e,label:t}){const o=[{label:Object(a.__)("Very difficult",'woocommerce'),value:"1"},{label:Object(a.__)("Somewhat difficult",'woocommerce'),value:"2"},{label:Object(a.__)("Neutral",'woocommerce'),value:"3"},{label:Object(a.__)("Somewhat easy",'woocommerce'),value:"4"},{label:Object(a.__)("Very easy",'woocommerce'),value:"5"}],[r,n]=Object(c.useState)(NaN),[i,l]=Object(c.useState)(""),[m,b]=Object(c.useState)(!1),[f,d]=Object(c.useState)(!0),p=()=>d(!1);return f?Object(c.createElement)(s.Modal,{className:"woocommerce-customer-effort-score",title:Object(a.__)("Please share your feedback",'woocommerce'),onRequestClose:p,shouldCloseOnClickOutside:!1},Object(c.createElement)(u.Text,{variant:"subtitle.small",as:"p",weight:"600",size:"14",lineHeight:"20px"},t),Object(c.createElement)("div",{className:"woocommerce-customer-effort-score__selection"},Object(c.createElement)(s.RadioControl,{selected:r.toString(10),options:o,onChange:e=>{const t=parseInt(e,10);n(t),b(!Number.isInteger(t))}})),(1===r||2===r)&&Object(c.createElement)("div",{className:"woocommerce-customer-effort-score__comments"},Object(c.createElement)(s.TextareaControl,{label:Object(a.__)("Comments (Optional)",'woocommerce'),help:Object(a.__)("Your feedback will go to the WooCommerce development team",'woocommerce'),value:i,onChange:e=>l(e),rows:5})),m&&Object(c.createElement)("div",{className:"woocommerce-customer-effort-score__errors",role:"alert"},Object(c.createElement)(u.Text,{variant:"body",as:"p"},Object(a.__)("Please provide feedback by selecting an option above.",'woocommerce'))),Object(c.createElement)("div",{className:"woocommerce-customer-effort-score__buttons"},Object(c.createElement)(s.Button,{isTertiary:!0,onClick:p,name:"cancel"},Object(a.__)("Cancel",'woocommerce')),Object(c.createElement)(s.Button,{isPrimary:!0,onClick:()=>{Number.isInteger(r)?(d(!1),e(r,i)):b(!0)},name:"send"},Object(a.__)("Send",'woocommerce')))):null}m.propTypes={recordScoreCallback:n.a.func.isRequired,label:n.a.string.isRequired};var b=m;const f=()=>{};function d({recordScoreCallback:e,label:t,createNotice:o,onNoticeShownCallback:r=f,onNoticeDismissedCallback:n=f,onModalShownCallback:i=f,icon:l}){const[s,u]=Object(c.useState)(!0),[m,d]=Object(c.useState)(!1);return Object(c.useEffect)(()=>{s&&(o("success",t,{actions:[{label:Object(a.__)("Give feedback",'woocommerce'),onClick:()=>{d(!0),i()}}],icon:l,explicitDismiss:!0,onDismiss:n}),u(!1),r())},[s]),s?null:m?Object(c.createElement)(b,{label:t,recordScoreCallback:e}):null}d.propTypes={recordScoreCallback:n.a.func.isRequired,label:n.a.string.isRequired,createNotice:n.a.func.isRequired,onNoticeShownCallback:n.a.func,onNoticeDismissedCallback:n.a.func,onModalShownCallback:n.a.func,icon:n.a.element};t.default=Object(i.compose)(Object(l.withDispatch)(e=>{const{createNotice:t}=e("core/notices2");return{createNotice:t}}))(d)},47:function(e,t,o){"use strict";var c=o(48);function r(){}function n(){}n.resetWarningCache=r,e.exports=function(){function e(e,t,o,r,n,a){if(a!==c){var i=new Error("Calling PropTypes validators directly is not supported by the `prop-types` package. Use PropTypes.checkPropTypes() to call them. Read more at http://fb.me/use-check-prop-types");throw i.name="Invariant Violation",i}}function t(){return e}e.isRequired=e;var o={array:e,bool:e,func:e,number:e,object:e,string:e,symbol:e,any:e,arrayOf:t,element:e,elementType:e,instanceOf:t,node:e,objectOf:t,oneOf:t,oneOfType:t,shape:t,exact:t,checkPropTypes:n,resetWarningCache:r};return o.PropTypes=o,o}},48:function(e,t,o){"use strict";e.exports="SECRET_DO_NOT_PASS_THIS_OR_YOU_WILL_BE_FIRED"},7:function(e,t){e.exports=window.wp.data}});