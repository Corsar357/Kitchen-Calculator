(function(wp){
  const el = wp.element.createElement;
  const { registerBlockType } = wp.blocks;
  const { PanelBody, RangeControl } = wp.components;
  const { InspectorControls } = wp.blockEditor || wp.editor;

  registerBlockType('kc/calculator', {
    title: 'Kitchen Calculator',
    category: 'widgets',
    icon: 'calculator',
    attributes: {
      defaultIngredient: { type:'string', default:'semolina' },
      defaultFromUnit:   { type:'string', default:'glass200' },
      defaultToUnit:     { type:'string', default:'g' },
      precision:         { type:'number', default:2 }
    },
    edit: function({ attributes, setAttributes }) {
      return el('div', { className:'kc-calculator-block' },
        el(InspectorControls, {},
          el(PanelBody, { title:'Settings' },
            el(RangeControl, {
              label: 'Precision',
              value: attributes.precision,
              onChange: v => setAttributes({ precision:v }),
              min:0, max:6
            })
          )
        ),
        el('p', null, 'Preview — form renders on the front end.')
      );
    },
    save: function(){ return null; }
  });
})(window.wp);
