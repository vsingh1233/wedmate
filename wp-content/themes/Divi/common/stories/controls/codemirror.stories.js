// External dependencies.
import React from 'react';

// Internal dependencies.
import ETBuilderControlCodeMirror from '../../controls/codemirror/codemirror.jsx';
import 'codemirror/lib/codemirror.css';
import 'codemirror/addon/hint/show-hint.css';
import 'codemirror/addon/search/matchesonscrollbar.css';
import 'codemirror/addon/dialog/dialog.css';
import 'codemirror/addon/display/fullscreen.css';
import 'codemirror-colorpicker/addon/colorpicker/colorpicker.css';
import 'codemirror-colorpicker/dist/codemirror-colorpicker.css';
import 'codemirror-colorpicker';


export default {
  title: 'Controls/Codemirror',
  component: ETBuilderControlCodeMirror,
  render: (args) => {
    return (
      <div style={{ width:'80vw' }}>
        <ETBuilderControlCodeMirror
          {...args}
        />
      </div>
    );
  },
  argTypes: {
    _onChange: { action: 'changed', table: { disable: true } },
    search: { table: { disable: true } },
    value: { table: { disable: true } },
    mode: {
      options: ['html', 'css'],
      control: { type: 'select' },
    },
  },
};

export const Default = {
  args: {
    className: 'code-snippet',
    inline: true,
    lint: true,
    search: '',
    value: '',
    name: 'defaultCodeMirror',
    mode: 'css',
  },
};

export const HTMLMode = {
  args: {
    className: 'code-snippet',
    inline: true,
    lint: true,
    search: '',
    value: '',
    name: 'htmlCodeMirror',
    mode: 'html',
  },
};
