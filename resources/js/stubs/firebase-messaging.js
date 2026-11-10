// Erstatning for 'firebase/messaging': push-pluginets web-del bruges ikke (push kører kun i den indbyggede app),
// så vi undgår at hente hele Firebase-web-SDK'et ind i projektet.
const nope = () => { throw new Error('Firebase web messaging bruges ikke i klubsystemet'); };
export const isSupported = async () => false;
export const getMessaging = nope;
export const getToken = nope;
export const deleteToken = nope;
export const onMessage = nope;
