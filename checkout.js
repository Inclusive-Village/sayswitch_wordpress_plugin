const settings = window.wc.wcSettings.getSetting("sayswitch_data", {});
const label =
  window.wp.htmlEntities.decodeEntities(settings.title) || "Pay with SaySwitch";

const Content = () => {
  return window.wp.htmlEntities.decodeEntities(settings.description || "");
};

const SaySwitchBlock = {
  name: "sayswitch",
  label: label,
  content: Object(window.wp.element.createElement)(Content, null),
  edit: Object(window.wp.element.createElement)(Content, null),
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    features: settings.supports,
  },
};

window.wc.wcBlocksRegistry.registerPaymentMethod(SaySwitchBlock);
