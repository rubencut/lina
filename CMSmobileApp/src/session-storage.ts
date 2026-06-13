import * as SecureStore from "expo-secure-store";

import type { User } from "@/types";

const TOKEN_KEY = "cms_mobile_token";
const USER_KEY = "cms_mobile_user";
const API_URL_KEY = "cms_mobile_api_url";

export async function loadSession() {
  const [token, userJson, apiUrl] = await Promise.all([
    SecureStore.getItemAsync(TOKEN_KEY),
    SecureStore.getItemAsync(USER_KEY),
    SecureStore.getItemAsync(API_URL_KEY),
  ]);

  return {
    token,
    apiUrl,
    user: userJson ? (JSON.parse(userJson) as User) : null,
  };
}

export async function saveSession(token: string, user: User, apiUrl: string) {
  await Promise.all([
    SecureStore.setItemAsync(TOKEN_KEY, token),
    SecureStore.setItemAsync(USER_KEY, JSON.stringify(user)),
    SecureStore.setItemAsync(API_URL_KEY, apiUrl),
  ]);
}

export async function saveApiUrl(apiUrl: string) {
  await SecureStore.setItemAsync(API_URL_KEY, apiUrl);
}

export async function clearSession() {
  await Promise.all([
    SecureStore.deleteItemAsync(TOKEN_KEY),
    SecureStore.deleteItemAsync(USER_KEY),
  ]);
}
