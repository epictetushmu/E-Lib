/** Claims decoded from the JWT payload minted by UserController::handleLogin. */
export interface JwtClaims {
  user_id: string
  email: string
  isAdmin: boolean
  iat: number
  exp: number
}
