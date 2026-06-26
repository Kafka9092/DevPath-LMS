import React from 'react';
import ReactDOM from 'react-dom/client';
import {Switch} from "@radix-ui/react-switch";
import { Button } from '@radix-ui/react-button';
import {TextArea} from '@radix-ui/react-textarea';
import {Input} from '@radix-ui/react-input';

function Example() {
    return (
        <div className="container">
            <div className="row justify-content-center">
                <div className="col-md-8">
                    <div className="card">
                        <div className="card-header">Example Component</div>

                            <Switch defaultChecked />
                            <Button defaultChecked />
                            <TextArea defaultChecked/>
                            <Input defaultChecked/>
                        <div className="card-body">I'm an example component!</div>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default Example;

if (document.getElementById('example')) {
    const Index = ReactDOM.createRoot(document.getElementById("example"));

    Index.render(
        <React.StrictMode>
            <Example/>

        </React.StrictMode>
    )
}
