package com.dgv6.app.api

import retrofit2.Response
import retrofit2.http.*

interface VtuApiService {

    // ── Auth ──────────────────────────────────────────────────────────────
    @POST("web/api/login")
    suspend fun login(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/register")
    suspend fun register(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    // ── Profile & Site ────────────────────────────────────────────────────
    @POST("web/api/profile")
    suspend fun getProfile(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/services")
    suspend fun getServices(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @GET("web/api/site-info")
    suspend fun getSiteInfo(): Response<Map<String, Any>>

    // ── Transactions ──────────────────────────────────────────────────────
    @POST("web/api/transactions")
    suspend fun getTransactions(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/requery")
    suspend fun requeryTransaction(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    // ── VTU Services ──────────────────────────────────────────────────────
    @POST("web/api/airtime")
    suspend fun purchaseAirtime(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/data")
    suspend fun purchaseData(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/cable")
    suspend fun purchaseCable(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/electric")
    suspend fun purchaseElectric(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/exam")
    suspend fun purchaseExam(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/sms")
    suspend fun sendBulkSms(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    // ── Plans ─────────────────────────────────────────────────────────────
    @POST("web/api/airtime-plans")
    suspend fun getAirtimePlans(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/data-plans")
    suspend fun getDataPlans(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/cable-plans")
    suspend fun getCablePlans(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/electric-plans")
    suspend fun getElectricPlans(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/exam-plans")
    suspend fun getExamPlans(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/sms-plans")
    suspend fun getSmsPricePlans(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    // ── Verification helpers ──────────────────────────────────────────────
    @POST("web/api/identify-network")
    suspend fun identifyNetwork(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/verify-cable")
    suspend fun verifyCable(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/verify-electric")
    suspend fun verifyElectric(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/verify-betting")
    suspend fun verifyBetting(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/betting-plans")
    suspend fun getBettingPlatforms(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/betting")
    suspend fun purchaseBetting(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/check-limit")
    suspend fun checkLimit(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/batch-status")
    suspend fun getBatchStatus(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    // ── Print Hub (Data/Recharge Cards) ───────────────────────────────────
    @GET("web/api/databundle-card-plans")
    suspend fun getPrintCardPlans(@QueryMap params: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/databundle-card")
    suspend fun buyPrintCards(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    // ── SMS Management ────────────────────────────────────────────────────
    @POST("web/api/sms-sender-ids")
    suspend fun getSenderIds(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/submit-sender-id")
    suspend fun submitSenderId(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    // ── Gift Cards ────────────────────────────────────────────────────────
    @POST("web/api/gift-card")
    suspend fun giftCardAction(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    // ── Virtual Cards ─────────────────────────────────────────────────────
    @POST("web/api/virtual-card")
    suspend fun virtualCardAction(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    // ── Crypto ────────────────────────────────────────────────────────────
    @POST("web/api/crypto")
    suspend fun cryptoAction(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/exchange-rate")
    suspend fun getExchangeRate(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    // ── Wallet ────────────────────────────────────────────────────────────
    @POST("web/api/share-fund")
    suspend fun shareFund(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/withdrawal")
    suspend fun withdrawToBank(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/verify-bank")
    suspend fun verifyBank(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/virtual-banks")
    suspend fun getVirtualBanks(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/funding-config")
    suspend fun getFundingConfig(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/get-charges")
    suspend fun getCharges(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/create-checkout")
    suspend fun createCheckout(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/platform-banks")
    suspend fun getPlatformBanks(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/fund-manual")
    suspend fun notifyManualFund(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/verify-funding")
    suspend fun verifyFunding(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    // ── Security ──────────────────────────────────────────────────────────
    @POST("web/api/set-pin")
    suspend fun setPin(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    // ── Contacts ──────────────────────────────────────────────────────────
    @POST("web/api/contacts")
    suspend fun contactsAction(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    // ── App Update ────────────────────────────────────────────────────────
    @GET("web/api/app-update")
    suspend fun checkAppUpdate(): Response<Map<String, Any>>

    @POST("web/api/register-device")
    suspend fun registerDevice(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    // ── NIN Card ──────────────────────────────────────────────────────────
    @POST("web/api/nin-card")
    suspend fun lookupNIN(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    // ── BVN Verify ────────────────────────────────────────────────────────
    @POST("web/api/bvn-verify")
    suspend fun verifyBVN(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    // ── KYC Verification ─────────────────────────────────────────────────
    @POST("web/api/kyc")
    suspend fun kycAction(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>

    @POST("web/api/kyc")
    suspend fun kycUpload(@Body body: okhttp3.MultipartBody): Response<Map<String, Any>>

    // ── AI Suite ──────────────────────────────────────────────────────────
    @POST("api/app-backend/ai-intent-parser.php")
    suspend fun parseAiIntent(
        @Header("Authorization") token: String,
        @Body body: @JvmSuppressWildcards Map<String, Any>
    ): Response<Map<String, Any>>

    @POST("api/app-backend/ai-vision-parser.php")
    suspend fun parseAiVision(
        @Header("Authorization") token: String,
        @Body body: @JvmSuppressWildcards Map<String, Any>
    ): Response<Map<String, Any>>
}


