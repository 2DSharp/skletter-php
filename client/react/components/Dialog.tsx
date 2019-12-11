import React, {Component} from "react";
import Button from "./Button";

export interface DialogProps {
  heading: string;
  content: Object;
  closable: boolean;
  overlayed: boolean;
}

class Dialog extends Component<DialogProps, { dialogDisplayed: boolean }> {

  constructor(props: DialogProps) {
    super(props);
    this.closeDialog = this.closeDialog.bind(this);
    this.state = {dialogDisplayed: true};
  }

  render() {
    const closable = this.props.closable;
    return (
        <div>
          {this.state.dialogDisplayed && (
              <div> {
                this.props.overlayed && <div onClick={this.closeDialog} className="overlay"/>
              }
                <div className="dialog-container centered">
                  <div className="header">
                    {closable && <Button action={this.closeDialog} type="close"/>}
                    <span><b style={{textAlign: "left", margin: "20px"}}>{this.props.heading}</b></span>
                  </div>
                  <div className="dialog-content">
                    {this.props.content}
                  </div>
                </div>
              </div>
          )}
        </div>
    );
  }

  closeDialog() {
    console.log("Closing");
    this.setState({dialogDisplayed: false});
  }
}

export default Dialog;
